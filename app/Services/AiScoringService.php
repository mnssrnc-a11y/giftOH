<?php

namespace App\Services;

use App\Repositories\FirebaseFundingRepository;
use App\Services\Ai\AiClient;

/**
 * Scores how critical a funding request is (0-100) with whichever free AI provider is available,
 * and stores the result on the request in Firebase (ai_score, ai_breakdown, ai_reasoning...).
 */
class AiScoringService
{
    private const CRITERIA = ['urgency', 'impact', 'need_severity', 'feasibility', 'category_fit'];

    public function __construct(
        private AiClient $ai,
        private FirebaseFundingRepository $requests,
        private FundingRules $rules
    ) {
    }

    /**
     * Score a request and save the result. Returns the stored fields, or null when no AI answered.
     */
    public function scoreAndStore(string $requestId): ?array
    {
        $request = $this->requests->findById($requestId);
        if ($request === null) {
            return null;
        }

        $result = $this->score($request);
        if ($result === null) {
            return null;
        }

        $this->requests->update($requestId, $result);

        return $result;
    }

    public function score(array $request): ?array
    {
        $category = $request['category_name'] ?? $request['category'] ?? 'General';
        $budget = (array) ($request['budget'] ?? []);
        $items = collect($budget['items'] ?? [])->map(fn (array $item): string => "{$item['name']} × {$item['qty']} @ ₱{$item['unit_price']}")->implode('; ');

        $system = <<<PROMPT
You evaluate funding requests for "Gift of Hope", a Philippine foundation that helps charity homes and communities.
Score how critical the request is from 0 to 100 (higher = more critical, must be funded) using:
urgency 25%, impact 25%, need severity 20%, feasibility 15%, category fit 15%.
Requests cover 20 to 40 beneficiaries with a per-person budget of goods (food, medicine, cleaning materials).
Return JSON: {"total_score": number, "breakdown": {"urgency": number, "impact": number, "need_severity": number,
"feasibility": number, "category_fit": number}, "recommendation": "critical|high|moderate|low",
"reasoning": "1-2 sentences", "red_flags": ["short notes on anything to verify during the social worker interview"]}
PROMPT;

        $prompt = implode("\n", array_filter([
            "Organization: " . ($request['org_name'] ?? 'Unknown'),
            "Category: {$category}",
            'Purpose: ' . ($request['purpose'] ?? $request['mission'] ?? ''),
            'Beneficiaries: ' . ($request['beneficiary_count'] ?? 'not stated'),
            isset($budget['per_person']) ? "Per-person budget: ₱{$budget['per_person']}" : null,
            $items !== '' ? "Budget items per person: {$items}" : null,
            (float) ($request['amount_requested'] ?? 0) > 0
                ? 'Budget set by the foundation: ₱' . $request['amount_requested']
                : 'Budget: not yet decided (the foundation sets it after the assessment)',
        ]));

        $answer = $this->ai->json($system, $prompt);
        $data = $answer['data'] ?? null;
        if (! is_array($data) || ! is_numeric($data['total_score'] ?? null)) {
            return null;
        }

        $breakdown = [];
        foreach (self::CRITERIA as $key) {
            $breakdown[$key] = max(0, min(100, round((float) ($data['breakdown'][$key] ?? 0), 1)));
        }

        return [
            'ai_score' => max(0, min(100, round((float) $data['total_score'], 1))),
            'ai_breakdown' => $breakdown,
            'ai_recommendation' => in_array($data['recommendation'] ?? '', ['critical', 'high', 'moderate', 'low'], true) ? $data['recommendation'] : null,
            'ai_reasoning' => mb_substr((string) ($data['reasoning'] ?? ''), 0, 600),
            'ai_red_flags' => array_slice(array_values(array_filter(array_map('strval', (array) ($data['red_flags'] ?? [])))), 0, 5),
            'ai_provider' => "{$answer['provider']} · {$answer['model']}",
            'ai_scored_at' => now()->toIso8601String(),
        ];
    }
}
