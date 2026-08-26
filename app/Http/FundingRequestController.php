<?php

use Illuminate\Http\Request;
use App\Services\FundingService;
use App\Services\AiScoringService;
use App\Services\VerificationService;
use App\Services\FirebaseService;

class FundingRequestController {
    public function __construct(
        private FundingService $fundingService,
        private AiScoringService $aiScoring,
    ) {}

    public function store(Request $request) {
        $fundingRequest = $this->fundingService->createRequest(
            $request->validated()
        );
        return redirect()->route('funding.show', $fundingRequest['id']);
    }

    public function show(string|int $id) {
        $fundingRequest = $this->fundingService->getRequestById($id);
        return view('funding.show', compact('fundingRequest'));
    }
}
