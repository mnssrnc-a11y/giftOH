<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Funding;
use App\Services\AiScoringService;

class aiActionController extends Controller
{

    public static function scoreFundingRequest(Funding $fundingRequest): ?array
    {
        return app(AiScoringService::class)->scoreFundingRequest($fundingRequest);
    }
}
