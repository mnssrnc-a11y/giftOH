<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Funding;

class aiActionController extends Controller
{
    /**
     * Send a chat request to Gemini AI and return the parsed JSON response.
     */
    function aiChat($systemPrompt, $userMessage) {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(
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

        $rawText = $response->json('candidates.0.content.parts.0.text');
        $result  = json_decode($rawText, true);

        return $result;
    }

    /**
     * Score a funding request using Gemini AI.
     *
     * Evaluates the request across 5 dimensions:
     *  - Urgency (25%): How time-sensitive is the need
     *  - Impact (25%): How many people benefit, significance of outcome
     *  - Need Severity (20%): How dire is the situation described
     *  - Feasibility (15%): Is the amount reasonable, is the plan realistic
     *  - Category Fit (15%): Based on category weight and approval priority
     *
     * @param  Funding  $fundingRequest  The funding request to score
     * @return array|null  Scoring result or null on failure
     */
    public static function scoreFundingRequest(Funding $fundingRequest): ?array
    {
        try {
            $controller = new self();

            // Load relationships if not already loaded
            $fundingRequest->loadMissing(['category', 'user']);

            // Build context about the category's scoring weight and priority
            $categoryWeight = $fundingRequest->category->weight_in_scoring ?? 1.00;
            $categoryPriority = $fundingRequest->category->approval_priority ?? 0;
            $categoryName = $fundingRequest->category->category_name ?? 'General';

            $systemPrompt = <<<PROMPT
You are an AI funding request evaluator for "Gift of Hope", a charity platform.
Your job is to analyze each funding request and produce a criticality score from 0 to 100.

A HIGHER score means the request is MORE CRITICAL and MUST receive funding.
A LOWER score means the request is LESS urgent or less necessary to fund.

You must evaluate the request across these 5 dimensions, each scored 0-100:

1. **Urgency (weight: 25%)** - How time-sensitive is this need? Emergency situations, medical needs, and disaster relief score highest. Long-term, non-urgent projects score lower.

2. **Impact (weight: 25%)** - How many people will benefit? How significant is the positive outcome? Requests affecting many people or producing life-changing results score highest.

3. **Need Severity (weight: 20%)** - How dire is the situation? Life-threatening or critical survival needs (food, shelter, medical) score highest. Nice-to-have improvements score lower.

4. **Feasibility (weight: 15%)** - Is the requested amount reasonable for the described purpose? Is the plan realistic and achievable? Well-structured, reasonable requests score highest.

5. **Category Fit (weight: 15%)** - The category "{$categoryName}" has an organizational priority weight of {$categoryWeight} and approval priority level of {$categoryPriority}. Higher values mean the organization considers this category more important.

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

            $result = $controller->aiChat($systemPrompt, $userMessage);

            // Validate the response structure
            if (
                $result &&
                isset($result['total_score']) &&
                isset($result['breakdown']) &&
                isset($result['recommendation']) &&
                isset($result['reasoning'])
            ) {
                // Clamp total_score between 0-100
                $result['total_score'] = max(0, min(100, round($result['total_score'], 2)));

                // Clamp each breakdown score
                foreach (['urgency', 'impact', 'need_severity', 'feasibility', 'category_fit'] as $key) {
                    if (isset($result['breakdown'][$key])) {
                        $result['breakdown'][$key] = max(0, min(100, round($result['breakdown'][$key], 2)));
                    }
                }

                return $result;
            }

            Log::warning('AI scoring returned invalid structure', ['result' => $result, 'request_id' => $fundingRequest->id]);
            return null;

        } catch (\Exception $e) {
            Log::error('AI scoring failed', [
                'request_id' => $fundingRequest->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
