<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Recommends how much of a funding request can be granted, based on the funds that are
 * actually available and how the request ranks against everything else in the queue.
 *
 * The rule-based allocation always runs so the admin gets an answer even without an AI key.
 * When a Gemini key is configured, the model may adjust the amount inside the same hard
 * limits (never above what was requested or what is available) and explains its reasoning.
 */
class FundAllocationAdvisor
{
    /** Share of available funds kept aside as an emergency buffer. */
    private const RESERVE_RATIO = 0.10;

    /** Mirrors the super admin's default category scores. */
    private const DEFAULT_PRIORITIES = [
        'medical' => 95,
        'healthcare' => 95,
        'education' => 85,
        'shelter' => 80,
        'food & shelter' => 80,
        'food' => 75,
        'community' => 70,
    ];

    private const FALLBACK_PRIORITY = 70;

    public function __construct(
        private FundingService $funding,
        private IotService $iot,
        private FirebaseService $firebase
    ) {
    }

    public function recommend(string $requestId): ?array
    {
        $request = $this->funding->getRequestById($requestId);
        if ($request === null) {
            return null;
        }

        $totalFunds = $this->iot->getTotalFunds();
        if ($totalFunds === null) {
            return [
                'available' => false,
                'message' => 'Live fund totals could not be read from Firebase, so no recommendation can be made right now.',
            ];
        }

        $priorities = $this->categoryPriorities();
        $requested = (float) ($request['amount_requested'] ?? $request['amount'] ?? 0);
        $committed = $this->funding->getTotalApprovedAmount();
        $reserved = $this->funding->getReservedAmount($requestId);
        $availableFunds = max(0.0, $totalFunds - $committed - $reserved);
        $budget = $availableFunds * (1 - self::RESERVE_RATIO);

        // Everything still competing for the same money: this request plus the rest of the pending queue.
        $queue = $this->funding->getPendingRequests();
        if (! collect($queue)->contains(fn (array $item): bool => (string) ($item['id'] ?? '') === $requestId)) {
            $queue[] = $request;
        }

        $priority = $this->priorityOf($request, $priorities);
        $queueDemand = 0.0;
        $weightedDemand = 0.0;
        foreach ($queue as $item) {
            $amount = (float) ($item['amount_requested'] ?? $item['amount'] ?? 0);
            $queueDemand += $amount;
            $weightedDemand += $amount * ($this->priorityOf($item, $priorities) / 100) ** 2;
        }

        if ($queueDemand <= $budget) {
            $amount = $requested;
        } else {
            $weight = $requested * ($priority / 100) ** 2;
            $share = $weightedDemand > 0 ? $budget * $weight / $weightedDemand : 0;
            $amount = min($requested, $share);
        }
        $amount = $this->roundDown($amount);

        $recommendation = [
            'available' => true,
            'source' => 'rules',
            'recommended_amount' => $amount,
            'requested_amount' => $requested,
            'coverage' => $requested > 0 ? round($amount / $requested * 100) : 0,
            'max_amount' => $this->roundDown(min($requested, $availableFunds)),
            'total_funds' => $totalFunds,
            'committed_funds' => $committed,
            'reserved_funds' => $reserved,
            'available_funds' => $availableFunds,
            'reserve_ratio' => self::RESERVE_RATIO,
            'queue_size' => count($queue),
            'queue_demand' => $queueDemand,
            'priority_score' => $priority,
            'priority_label' => $this->priorityLabel($priority),
            'reasoning' => $this->ruleReasoning($request, $priority, $requested, $amount, $availableFunds, $queueDemand, $budget, count($queue)),
        ];

        return $this->refineWithGemini($request, $recommendation) ?? $recommendation;
    }

    /**
     * Category scores managed by the super admin, stored at system_settings/category_priorities.
     */
    private function categoryPriorities(): array
    {
        $priorities = self::DEFAULT_PRIORITIES;

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

    private function roundDown(float $amount): float
    {
        // Round to a clean figure an admin would actually release.
        $step = $amount >= 10000 ? 500 : ($amount >= 1000 ? 100 : 10);

        return max(0.0, floor($amount / $step) * $step);
    }

    private function ruleReasoning(array $request, float $priority, float $requested, float $amount, float $available, float $queueDemand, float $budget, int $queueSize): array
    {
        $peso = fn (float $value): string => '₱' . number_format($value, 2);
        $category = $request['category_name'] ?? $request['category'] ?? 'General';
        $lines = [
            "{$category} carries a priority score of {$priority}/100 ({$this->priorityLabel($priority)}).",
            "After approved grants and other recommendations awaiting finalization, {$peso($available)} is available; "
                . (int) (self::RESERVE_RATIO * 100) . '% is held back as a reserve.',
        ];

        if ($available <= 0) {
            $lines[] = 'There are no unallocated funds right now, so nothing can be released until new donations arrive.';
        } elseif ($queueDemand <= $budget) {
            $lines[] = "The {$queueSize} request(s) in the queue ask for {$peso($queueDemand)} in total, which the budget covers, so the full amount can be granted.";
        } else {
            $lines[] = "The {$queueSize} request(s) in the queue ask for {$peso($queueDemand)}, more than the {$peso($budget)} budget, so funds are shared by priority-weighted need.";
            $lines[] = "This request receives {$peso($amount)} of the {$peso($requested)} requested.";
        }

        return $lines;
    }

    private function refineWithGemini(array $request, array $recommendation): ?array
    {
        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            return null;
        }

        $context = json_encode([
            'request' => [
                'organization' => $request['org_name'] ?? null,
                'category' => $request['category_name'] ?? $request['category'] ?? null,
                'mission' => $request['mission'] ?? $request['description'] ?? null,
                'amount_requested' => $recommendation['requested_amount'],
                'ai_criticality_score' => $request['ai_score'] ?? null,
            ],
            'funds' => [
                'available' => $recommendation['available_funds'],
                'reserve_ratio' => $recommendation['reserve_ratio'],
                'pending_queue_size' => $recommendation['queue_size'],
                'pending_queue_demand' => $recommendation['queue_demand'],
            ],
            'priority_score' => $recommendation['priority_score'],
            'rule_based_amount' => $recommendation['recommended_amount'],
            'hard_maximum' => $recommendation['max_amount'],
        ], JSON_UNESCAPED_UNICODE);

        $systemPrompt = <<<PROMPT
You advise administrators of "Gift of Hope", a charity that funds charity homes, on how much money to release for a funding request.
You are given the request, the funds actually available, the pending queue, and a rule-based amount.
Recommend an amount in PHP between 0 and hard_maximum. Stay close to rule_based_amount unless the request details justify a change,
and never starve the rest of the queue. Return ONLY JSON: {"recommended_amount": <number>, "reasoning": ["<short sentence>", "..."]} with at most 3 sentences.
PROMPT;

        try {
            $response = Http::timeout(20)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey,
                [
                    'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $context]]]],
                    'generationConfig' => ['responseMimeType' => 'application/json'],
                ]
            );

            $result = json_decode((string) $response->json('candidates.0.content.parts.0.text'), true);
            if (! is_array($result) || ! is_numeric($result['recommended_amount'] ?? null)) {
                return null;
            }

            $amount = $this->roundDown(max(0, min((float) $result['recommended_amount'], $recommendation['max_amount'])));
            $reasoning = array_values(array_filter(array_map('strval', (array) ($result['reasoning'] ?? []))));

            return array_merge($recommendation, [
                'source' => 'gemini',
                'recommended_amount' => $amount,
                'coverage' => $recommendation['requested_amount'] > 0 ? round($amount / $recommendation['requested_amount'] * 100) : 0,
                'reasoning' => $reasoning !== [] ? array_slice($reasoning, 0, 3) : $recommendation['reasoning'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Gemini fund recommendation failed; using rule-based amount.', ['error' => $exception->getMessage()]);

            return null;
        }
    }
}
