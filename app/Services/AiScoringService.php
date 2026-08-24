<?php

namespace App\Services;

use App\Models\Funding;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiScoringService
{
    public function scoreFundingRequest(Funding $fundingRequest): ?array
    {
        try {
            $fundingRequest->loadMissing(['category', 'user']);

            $categoryWeight = $fundingRequest->category->weight_in_scoring ?? 1.00;
            $categoryPriority = $fundingRequest->category->approval_priority ?? 0;
            $categoryName = $fundingRequest->category->category_name ?? 'General';

            $systemPrompt = <<<PROMPT
You are an AI funding request evaluator for "Gift of Hope", a charity platform.
Your job is to analyze each funding request and produce a criticality score from 0 to 100.

A HIGHER score means the request is MORE CRITICAL and MUST receive funding.
A LOWER score means the request is LESS urgent or less necessary to fund.

Evaluate the request across urgency (25%), impact (25%), need severity (20%), feasibility (15%), and category fit (15%).
The category is "{$categoryName}", with organizational priority weight {$categoryWeight} and approval priority {$categoryPriority}.

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

Title: {$fundingRequest->title}
Category: {$categoryName}
Amount Requested: {$fundingRequest->amount_requested}
Description: {$fundingRequest->description}
Submitted by: {$fundingRequest->user->fname} {$fundingRequest->user->lname}
Date Submitted: {$fundingRequest->created_at}
MSG;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . config('services.gemini.api_key'),
                    [
                        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                        'contents' => [['role' => 'user', 'parts' => [['text' => $userMessage]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json'],
                    ]
                );

            $result = json_decode((string) $response->json('candidates.0.content.parts.0.text'), true);

            if (!is_array($result) || !isset($result['total_score'], $result['breakdown'], $result['recommendation'], $result['reasoning'])) {
                Log::warning('AI scoring returned an invalid structure', ['request_id' => $fundingRequest->id]);
                return null;
            }

            $result['total_score'] = max(0, min(100, round((float) $result['total_score'], 2)));
            foreach (['urgency', 'impact', 'need_severity', 'feasibility', 'category_fit'] as $key) {
                if (isset($result['breakdown'][$key])) {
                    $result['breakdown'][$key] = max(0, min(100, round((float) $result['breakdown'][$key], 2)));
                }
            }

            return $result;
        } catch (\Throwable $exception) {
            Log::error('AI scoring failed', [
                'request_id' => $fundingRequest->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}