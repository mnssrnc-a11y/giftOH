<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class aiActionController extends Controller
{
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
}
