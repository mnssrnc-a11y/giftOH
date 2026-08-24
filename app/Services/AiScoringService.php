<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Funding;
use App\Models\FundingCategory;

/**
 * AiScoringService
 *
 * Handles AI-powered scoring of funding requests using the Gemini API.
 * Isolated from controller logic for better testability and reusability.
 *
 * @package App\Services
 */
class AiScoringService
{
    /**
     * Score a funding request using Gemini AI.
     *
     * @param Funding $fundingRequest
     * @return array|null
     */
    public function scoreFundingRequest(Funding $fundingRequest): ?array
    {
        try {
            $category = $fundingRequest->category ?? new FundingCategory();
            $categoryName = $category->category_name ?? 'General';
            $categoryWeight = $category->priority_weight ?? 1.0;
            $categoryPriority = $category->approval_priority ?? 'normal';

            $systemPrompt = <<<PROMPT
You are an expert social services evaluator for a charity funding platform. Your task is to score funding requests based on:

1. **Urgency** (0-100): How time-sensitive is this request? Medical emergencies score high, planned expenses score lower.
2. **Impact** (0-100): How many beneficiaries will be affected? Scale matters here.
3. **Need Severity** (0-100): How critical is the need? Basic necessities are higher priority than luxuries.
4. **Feasibility** (0-100): How realistic and achievable is solving this problem with the requested amount?
5. **Category Fit** (0-100): How well does this align with organizational priorities?

Calculate the total_score as: (urgency * 0.25) + (impact * 0.25) + (need_severity * 0.20) + (feasibility * 0.15) + (category_fit * 0.15)

Based on the total_score, assign a recommendation:
- 75-100: "critical" (Must provide funds immediately)
- 50-74: "high" (Should provide funds)
- 25-49: "moderate" (Can provide funds if available)
- 0-24: "low" (Funding not urgently needed)

Return ONLY valid JSON in this exact structure:
{
    "total_score": <number 0-100>,
    "breakdown": {
        "urgency": <number 0-100>,
        "impact": <number 0-100>,
        "need_severity": <number 0-100>,
        "feasibility": <number 0-100>,
        "category_fit": <number 0-100>
    },
    "recommendation": "<critical|high|moderate|low>",
    "reasoning": "<1-2 sentence explanation of why this score was given>"
}
PROMPT;

            $userMessage = <<<MSG
Please evaluate this funding request:

**Title:** {$fundingRequest->title}
**Category:** {$categoryName}
**Amount Requested:** ₱{$fundingRequest->amount_requested}
**Description:** {$fundingRequest->description}
**Submitted by:** {$fundingRequest->user->fname} {$fundingRequest->user->lname}
**Date Submitted:** {$fundingRequest->created_at}
MSG;

            return $this->callGeminiApi($systemPrompt, $userMessage);
        } catch (\Exception $e) {
            Log::error('AI Scoring failed', [
                'funding_id' => $fundingRequest->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Call the Gemini API with the given prompts.
     *
     * @param string $systemPrompt
     * @param string $userMessage
     * @return array|null
     */
    private function callGeminiApi(string $systemPrompt, string $userMessage): ?array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . env('GEMINI_API_KEY'),
                    [
                        'system_instruction' => [
                            'parts' => [['text' => $systemPrompt]]
                        ],
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [['text' => $userMessage]]
                            ]
                        ],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                        ],
                    ]
                );

            if (!$response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $rawText = $response->json('candidates.0.content.parts.0.text');
            $result = json_decode($rawText, true);

            // Validate response structure
            if (
                $result &&
                isset($result['total_score']) &&
                isset($result['breakdown']) &&
                isset($result['recommendation']) &&
                isset($result['reasoning'])
            ) {
                // Clamp total_score between 0-100
                $result['total_score'] = max(0, min(100, (int)$result['total_score']));
                return $result;
            }

            Log::warning('Invalid AI response structure', ['response' => $result]);
            return null;
        } catch (\Exception $e) {
            Log::error('Gemini API call failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
