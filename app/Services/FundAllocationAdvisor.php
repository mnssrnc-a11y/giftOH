<?php

namespace App\Services;

use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\Log;

/**
 * Recommends how much of a funding request can be granted: the amount the social worker decided
 * in the assessment, and when funds are short, a share of them by priority so the other requests
 * in the queue still get help. Requests not assessed yet count with the per-person guideline.
 *
 * The rule-based amount always runs. When an AI provider is configured it may adjust the
 * amount inside the same hard limits and explains its reasoning.
 */
class FundAllocationAdvisor
{
    /** Share of available funds kept aside as an emergency buffer. */
    private const RESERVE_RATIO = 0.10;

    private const FALLBACK_PRIORITY = 70;

    public function __construct(
        private FundingService $funding,
        private IotService $iot,
        private FirebaseService $firebase,
        private FundingRules $rules,
        private AiClient $ai
    ) {
    }

    public function recommend(string $requestId): ?array
    {
        $request = $this->funding->getRequestById($requestId);
        if ($request === null) {
            return null;
        }

        // Funds on hand (collected minus transfers) and what approved requests in the release line are owed.
        $totalFunds = $this->iot->getBalance();
        if ($totalFunds === null) {
            return [
                'available' => false,
                'message' => 'Live fund totals could not be read from Firebase, so no recommendation can be made right now.',
            ];
        }

        $priorities = $this->categoryPriorities();
        $committed = $this->funding->getAwaitingTransferAmount();
        $reserved = $this->funding->getReservedAmount($requestId);
        $availableFunds = max(0.0, $totalFunds - $committed - $reserved);
        $budget = $availableFunds * (1 - self::RESERVE_RATIO);

        // Everything still competing for the same money: this request plus the rest of the pending queue.
        $queue = $this->funding->getPendingRequests();
        if (! collect($queue)->contains(fn (array $item): bool => (string) ($item['id'] ?? '') === $requestId)) {
            $queue[] = $request;
        }

        $need = $this->needOf($request);
        $priority = $this->priorityOf($request, $priorities);
        $queueDemand = 0.0;
        $weightedDemand = 0.0;
        foreach ($queue as $item) {
            $itemNeed = $this->needOf($item)['amount'];
            $queueDemand += $itemNeed;
            $weightedDemand += $itemNeed * ($this->priorityOf($item, $priorities) / 100) ** 2;
        }

        if ($queueDemand <= $budget) {
            // Enough for everyone: exactly what the assessment says.
            $amount = $need['amount'];
        } else {
            $weight = $need['amount'] * ($priority / 100) ** 2;
            $amount = $this->roundToPeople(min($need['amount'], $weightedDemand > 0 ? $budget * $weight / $weightedDemand : 0), $need['people']);
        }

        $recommendation = [
            'available' => true,
            'source' => 'rules',
            'recommended_amount' => $amount,
            'per_person' => $need['people'] > 0 ? round($amount / $need['people'], 2) : null,
            'people' => $need['people'],
            'people_source' => $need['people_source'],
            'per_person_cap' => $need['cap'],
            'requested_amount' => $need['requested'],
            'coverage' => $need['requested'] > 0 ? round($amount / $need['requested'] * 100) : 0,
            'max_amount' => $need['requested'] <= $availableFunds ? $need['requested'] : $this->roundToPeople($availableFunds, $need['people']),
            'total_funds' => $totalFunds,
            'committed_funds' => $committed,
            'reserved_funds' => $reserved,
            'available_funds' => $availableFunds,
            'reserve_ratio' => self::RESERVE_RATIO,
            'queue_size' => count($queue),
            'queue_demand' => $queueDemand,
            'priority_score' => $priority,
            'priority_label' => $this->priorityLabel($priority),
            'reasoning' => $this->ruleReasoning($request, $need, $priority, $amount, $availableFunds, $queueDemand, $budget, count($queue)),
        ];

        return $this->refineWithAi($request, $recommendation) ?? $recommendation;
    }

    /**
     * How much the request needs: the amount the social worker / admin decided in the assessment.
     * Requests not assessed yet count with the foundation's per-person guideline as an estimate.
     *
     * @return array{amount: float, requested: float, people: int, people_source: string, cap: float, assessed: bool}
     */
    private function needOf(array $request): array
    {
        $category = $request['category_name'] ?? $request['category'] ?? null;
        $verified = (int) ($request['assessment']['verified_beneficiaries'] ?? 0);
        $declared = (int) ($request['beneficiary_count'] ?? 0);
        $people = $verified ?: $declared;
        $cap = $this->rules->perPersonCap($category);
        $assessed = $this->funding->amountNeeded($request);
        $amount = $assessed ?? $people * $cap;

        return [
            'amount' => $amount,
            'requested' => $amount,
            'people' => $people,
            'people_source' => $verified ? 'verified by the social worker' : 'declared in the request',
            'cap' => $cap,
            'assessed' => $assessed !== null,
        ];
    }

    /**
     * Category scores managed by the super admin, stored at system_settings/category_priorities.
     */
    private function categoryPriorities(): array
    {
        $priorities = $this->rules->defaultPriorities();

        try {
            $stored = $this->firebase->getDatabase()->getReference('system_settings/category_priorities')->getValue();
            foreach (is_array($stored) ? $stored : [] as $category => $score) {
                if (is_numeric($score)) {
                    $priorities[strtolower((string) $category)] = max(0, min(100, (float) $score));
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Category priorities could not be read; using defaults.', ['error' => $exception->getMessage()]);
        }

        return $priorities;
    }

    private function priorityOf(array $request, array $priorities): float
    {
        $category = strtolower((string) ($request['category_name'] ?? $request['category'] ?? ''));
        $categoryScore = $priorities[$category] ?? self::FALLBACK_PRIORITY;

        // Blend in the per-request AI criticality score when the request has been scored.
        if (isset($request['ai_score']) && is_numeric($request['ai_score'])) {
            return round(0.5 * $categoryScore + 0.5 * max(0, min(100, (float) $request['ai_score'])), 1);
        }

        return (float) $categoryScore;
    }

    private function priorityLabel(float $priority): string
    {
        return match (true) {
            $priority >= 90 => 'critical',
            $priority >= 80 => 'high',
            $priority >= 65 => 'moderate',
            default => 'low',
        };
    }

    /**
     * Express the amount as a whole per-person figure times the number of people
     * (₱10 steps per person, ₱1 steps when funds are so short that less than ₱100 each is possible).
     */
    private function roundToPeople(float $amount, int $people): float
    {
        if ($people <= 0) {
            $step = $amount >= 10000 ? 500 : ($amount >= 1000 ? 100 : 10);

            return max(0.0, floor($amount / $step) * $step);
        }

        $perPerson = $amount / $people;
        $step = $perPerson >= 100 ? 10 : 1;

        return max(0.0, floor($perPerson / $step) * $step * $people);
    }

    private function ruleReasoning(array $request, array $need, float $priority, float $amount, float $available, float $queueDemand, float $budget, int $queueSize): array
    {
        $peso = fn (float $value): string => '₱' . number_format($value, 2);
        $category = $request['category_name'] ?? $request['category'] ?? 'General';
        $lines = ["{$category} carries a priority score of {$priority}/100 ({$this->priorityLabel($priority)})."];

        if ($need['assessed']) {
            $lines[] = "The social worker's assessment puts the need at {$peso($need['amount'])}"
                . ($need['people'] > 0 ? " for {$need['people']} people ({$need['people_source']})." : '.');
        } elseif ($need['people'] > 0) {
            $lines[] = "Not assessed yet; estimated with the guideline: {$need['people']} people × {$peso($need['cap'])} per person = {$peso($need['amount'])}.";
        }

        $lines[] = "After the money owed to approved requests in the release line and recommendations awaiting finalization, {$peso($available)} is available; "
            . (int) (self::RESERVE_RATIO * 100) . '% is held back as a reserve.';

        if ($available <= 0) {
            $lines[] = 'There are no unallocated funds right now, so nothing can be released until new donations arrive.';
        } elseif ($queueDemand <= $budget) {
            $lines[] = "The {$queueSize} request(s) in the queue need {$peso($queueDemand)} in total, which the budget covers.";
        } else {
            $lines[] = "The {$queueSize} request(s) in the queue need {$peso($queueDemand)}, more than the {$peso($budget)} budget, so funds are shared by priority so other requests still receive a budget.";
        }

        if ($need['people'] > 0 && $amount > 0) {
            $lines[] = "Recommended: {$peso($amount / $need['people'])} per person × {$need['people']} = {$peso($amount)}.";
        }

        return $lines;
    }

    private function refineWithAi(array $request, array $recommendation): ?array
    {
        if (! $this->ai->hasProvider() || $recommendation['max_amount'] <= 0) {
            return null;
        }

        $context = json_encode([
            'request' => [
                'organization' => $request['org_name'] ?? null,
                'category' => $request['category_name'] ?? $request['category'] ?? null,
                'purpose' => $request['purpose'] ?? $request['mission'] ?? null,
                'amount_requested' => $recommendation['requested_amount'],
                'people' => $recommendation['people'],
                'how_the_amount_was_decided' => $request['assessment']['amount_basis'] ?? null,
                'social_worker_assessment' => $request['assessment']['notes'] ?? null,
                'ai_criticality_score' => $request['ai_score'] ?? null,
            ],
            'funds' => [
                'available' => $recommendation['available_funds'],
                'reserve_ratio' => $recommendation['reserve_ratio'],
                'pending_queue_size' => $recommendation['queue_size'],
                'pending_queue_need' => $recommendation['queue_demand'],
            ],
            'usual_per_person_allocation' => $recommendation['per_person_cap'],
            'priority_score' => $recommendation['priority_score'],
            'rule_based_amount' => $recommendation['recommended_amount'],
            'hard_maximum' => $recommendation['max_amount'],
        ], JSON_UNESCAPED_UNICODE);

        $system = <<<PROMPT
You advise administrators of "Gift of Hope", a Philippine charity, on how much money to release for a funding request.
A social worker decides the amount each request needs after an interview (requests cover 20-40 people); the foundation must leave money for other requests.
Recommend an amount in PHP between 0 and hard_maximum. Stay close to rule_based_amount unless the request details justify a change.
Return JSON: {"recommended_amount": number, "reasoning": ["short sentence", "..."]} with at most 3 sentences.
PROMPT;

        $answer = $this->ai->json($system, $context);
        $result = $answer['data'] ?? null;
        if (! is_array($result) || ! is_numeric($result['recommended_amount'] ?? null)) {
            return null;
        }

        $amount = $this->roundToPeople(max(0, min((float) $result['recommended_amount'], $recommendation['max_amount'])), $recommendation['people']);
        $reasoning = array_values(array_filter(array_map('strval', (array) ($result['reasoning'] ?? []))));

        return array_merge($recommendation, [
            'source' => 'ai',
            'provider' => "{$answer['provider']} · {$answer['model']}",
            'recommended_amount' => $amount,
            'per_person' => $recommendation['people'] > 0 ? round($amount / $recommendation['people'], 2) : null,
            'coverage' => $recommendation['requested_amount'] > 0 ? round($amount / $recommendation['requested_amount'] * 100) : 0,
            'reasoning' => $reasoning !== [] ? array_slice($reasoning, 0, 3) : $recommendation['reasoning'],
        ]);
    }
}
