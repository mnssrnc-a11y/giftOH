<?php

namespace App\Http\Controllers;

use App\Services\AiScoringService;
use App\Services\FundingRules;
use App\Services\FundingService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FundController extends Controller
{
    public function __construct(
        private FundingService $fundingService,
        private FundingRules $rules,
        private NotificationService $notifications
    ) {
    }

    public function storeFund(Request $request)
    {
        if ($denialReason = $this->fundingService->requestDenialReason(Auth::id())) {
            return back()->withErrors(['fund_request' => $denialReason])->withInput();
        }

        $categories = array_keys($this->rules->categories());
        $requirements = $this->rules->requirementsFor();
        $documentRule = config('funding.document_rules');

        $validated = $request->validate(array_merge([
            'org_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'address' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'category' => ['required', Rule::in($categories)],
            'purpose' => 'required|string|max:5000',
            'beneficiaries' => 'required|string|max:20000',
            'bank_name' => 'required|string|max:120',
            'account_name' => 'required|string|max:255',
            'account_number' => ['required', 'string', 'regex:/^[0-9 \-]{6,30}$/'],
        ], collect($requirements)->mapWithKeys(fn ($doc, $field) => [$field => "required|{$documentRule}"])->all()), [
            'beneficiaries.required' => 'List the people who will receive the help, one name per line.',
            'account_number.regex' => 'Enter a valid account number (digits only).',
            'category.in' => 'Choose one of the listed categories. Education / scholarship assistance is no longer offered.',
        ]);

        $names = $this->rules->parseBeneficiaries($validated['beneficiaries']);
        $min = (int) config('funding.beneficiaries.min');
        $max = (int) config('funding.beneficiaries.max');
        if (count($names) < $min || count($names) > $max) {
            throw ValidationException::withMessages([
                'beneficiaries' => "A request must list between {$min} and {$max} people; you listed " . count($names) . '.',
            ]);
        }

        if (! $this->rules->accountNameMatches($validated['account_name'], [$validated['org_name'], $validated['contact_person']])) {
            throw ValidationException::withMessages([
                'account_name' => 'Funds are only sent to an account in the receiver\'s name. The account name must match the organization name or the contact person exactly.',
            ]);
        }

        $documents = [];
        foreach (array_keys($requirements) as $field) {
            $documents[$field] = $request->file($field)->store('fund_documents/' . $field, config('funding.files_disk'));
        }

        $categoryName = $validated['category'];
        $created = $this->fundingService->createRequest([
            'user_id' => Auth::id(),
            'status_id' => 1,
            'status_name' => 'pending',
            'category' => $categoryName,
            'category_name' => $categoryName,
            'request_type' => 'assistance',
            'org_name' => $validated['org_name'],
            'contact_person' => $validated['contact_person'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['phone'],
            'address' => $validated['address'],
            'tax_id' => $validated['tax_id'] ?? null,
            'purpose' => $validated['purpose'],
            'mission' => $validated['purpose'],
            'beneficiaries' => $names,
            'beneficiary_count' => count($names),
            // The per-person budget (and so the amount) is decided by an admin after the assessment.
            'amount_requested' => 0,
            'documents' => $documents,
            'disbursement' => [
                'method' => 'bank_transfer',
                'bank_name' => $validated['bank_name'],
                'account_name' => $validated['account_name'],
                'account_number' => preg_replace('/[\s\-]/', '', $validated['account_number']),
            ],
        ]);

        // Score criticality with the AI providers once the response has been sent, so the user never waits.
        $requestId = $created['id'];
        dispatch(fn () => app(AiScoringService::class)->scoreAndStore($requestId))->afterResponse();
        $this->notifications->notifyAdmins($requestId, 'new_request', 'New funding request',
            "{$validated['org_name']} ({$categoryName}) asks help for " . count($names) . ' people. Schedule the social worker interview within ' . config('funding.assessment_days', 7) . ' days.');

        $days = (int) config('funding.assessment_days', 7);

        return redirect()->route('fund-request.show', $requestId)
            ->with('success', "Your request was submitted. A social worker will contact you for an interview and assessment within {$days} days.");
    }

    /**
     * Appeal a rejected request (at most twice) with a reason and an updated supporting document.
     * The request goes back to the admins for review.
     */
    public function appeal(Request $request, string $id)
    {
        $fundRequest = $this->fundingService->getRequestById($id);
        abort_if($fundRequest === null || (string) ($fundRequest['user_id'] ?? '') !== (string) Auth::id(), 404);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
            'document' => 'required|' . config('funding.document_rules'),
        ], [
            'reason.required' => 'Explain what has changed since the request was rejected.',
            'document.required' => 'Attach an updated supporting document.',
        ]);

        $path = $request->file('document')->store('fund_documents/appeals', config('funding.files_disk'));
        $updated = $this->fundingService->appeal($id, Auth::id(), trim($validated['reason']), $path);
        if ($updated === null) {
            Storage::disk(config('funding.files_disk'))->delete($path);

            return back()->with('alert_error', 'This request cannot be appealed. Only rejected requests can be appealed, at most ' . FundingService::MAX_APPEALS . ' times.');
        }

        $this->notifications->notifyAdmins($id, 'appeal', 'Rejected request appealed',
            ($fundRequest['org_name'] ?? 'A requester') . " appealed (appeal {$updated['appeals']} of " . FundingService::MAX_APPEALS . '): ' . mb_strimwidth($validated['reason'], 0, 140, '…'));

        return redirect()->route('fund-request.show', $id)
            ->with('success', 'Your appeal was sent. The foundation will review your request again and notify you of the decision.');
    }

    /**
     * After funds are released, the organization reports how the money was used.
     */
    public function submitLiquidation(Request $request, string $id)
    {
        $fundRequest = $this->fundingService->getRequestById($id);
        abort_if($fundRequest === null || (string) ($fundRequest['user_id'] ?? '') !== (string) Auth::id(), 404);

        $documentRule = config('funding.document_rules');
        $validated = $request->validate([
            'receipts' => 'required|array|min:1|max:10',
            'receipts.*' => $documentRule,
            'recipient_list' => "required|{$documentRule}",
            'recipient_names' => 'nullable|string|max:20000',
            'amount_spent' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'file|mimes:jpg,jpeg,png|max:10240',
        ], [
            'receipts.required' => 'Upload at least one receipt or liquidation photo.',
            'recipient_list.required' => 'Upload the list of people who received the donation (signed list or photo).',
        ]);

        $store = fn ($file, string $folder) => $file->store("liquidations/{$id}/{$folder}", config('funding.files_disk'));
        $files = [
            'receipts' => array_map(fn ($file) => $store($file, 'receipts'), $request->file('receipts')),
            'photos' => array_map(fn ($file) => $store($file, 'photos'), $request->file('photos', [])),
            'recipient_list' => $store($request->file('recipient_list'), 'recipients'),
        ];
        $updated = $this->fundingService->submitLiquidation($id, $files + [
            'recipient_names' => $this->rules->parseBeneficiaries($validated['recipient_names'] ?? ''),
            'amount_spent' => round((float) $validated['amount_spent'], 2),
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($updated === null) {
            // Nothing was recorded, so do not keep the uploaded files.
            Storage::disk(config('funding.files_disk'))->delete(array_merge($files['receipts'], $files['photos'], [$files['recipient_list']]));

            return back()->with('alert_error', 'A liquidation can only be submitted after the funds were released, and not while one is under review.');
        }

        $this->notifications->notifyAdmins($id, 'liquidation_submitted', 'Liquidation submitted',
            ($fundRequest['org_name'] ?? 'A requester') . ' reported ₱' . number_format((float) $validated['amount_spent'], 2) . ' spent with ' . count($files['receipts']) . ' receipt file(s). Review it.');

        return redirect()->route('fund-request.show', $id)
            ->with('success', 'Liquidation submitted. The foundation will review your receipts and list of recipients.');
    }
}
