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
            $validated = $request->validate([
        'org_name' => ['required', 'string', 'max:255'],
        'amount_requested' => ['required', 'numeric', 'min:1'],
        'category' => ['required', 'string', 'max:255'],
        'mission' => ['required', 'string'],
        'contact_person' => ['required', 'string', 'max:255'],
        'contact_email' => ['required', 'email', 'max:255'],
        'phone' => ['required', 'string', 'max:255'],
        'address' => ['required', 'string', 'max:255'],
        'tax_id' => ['required', 'string', 'max:255'],
        'financial_rprt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf|max:2048'],
        'doc_image' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf|max:2048'],
        'id_image' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf|max:2048'],
        'barangay_clr' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf|max:2048'],
    ]);

    $fundingRequest = $this->fundingService->createRequest($validated);

    return redirect()->route(
        'funding.show',
        $fundingRequest['id']
    );
    }

    public function show(string|int $id) {
        $fundingRequest = $this->fundingService->getRequestById($id);
        abort_if($fundingRequest === null, 404);
        return view('funding.show', compact('fundingRequest'));
    }


}
