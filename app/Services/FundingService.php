<?php

class FundingService {
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
}