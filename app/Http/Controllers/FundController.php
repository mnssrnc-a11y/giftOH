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
        $min = (int) config('funding.beneficiaries.min');
        $max = (int) config('funding.beneficiaries.max');
        $mode = (string) $request->input('transfer_mode');
        $accountKind = $this->rules->accountKind($mode, (string) $request->input('provider'));
        // "0917 123 4567" and "0917-123-4567" are the same number.
        $request->merge(array_filter([
            'phone' => $request->filled('phone') ? preg_replace('/[\s\-]/', '', (string) $request->input('phone')) : null,
            'account_number' => $accountKind !== 'email' && $request->filled('account_number') ? preg_replace('/[\s\-]/', '', (string) $request->input('account_number')) : null,
        ], fn ($value) => $value !== null));

        // Every rule runs before anything is reported, so the requester sees all missing fields at once.
        $validated = $request->validate(array_merge([
            'org_choice' => ['required', Rule::in(array_merge(config('funding.known_organizations'), ['other']))],
            'org_name_other' => 'required_if:org_choice,other|nullable|string|max:255',
            'contact_person' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'phone' => ['required', 'string', 'regex:/^(\+?63|0)9\d{9}$/'],
            'tax_id' => 'nullable|string|max:255',
            'street' => 'required|string|max:150',
            'barangay' => 'required|string|max:100',
            'municipality' => 'required|string|max:100',
            'categories' => 'required|array|min:1',
            'categories.*' => [Rule::in($categories)],
            'purpose' => 'required|string|max:5000',
            'beneficiary_count' => "required|integer|min:{$min}|max:{$max}",
            'transfer_mode' => ['required', Rule::in(array_keys(config('funding.transfer_modes')))],
            'provider' => ['required', Rule::in($this->rules->transferProviders($mode))],
            'account_name' => 'required|string|max:255',
            'account_number' => match ($accountKind) {
                'mobile' => ['required', 'string', 'regex:/^(\+?63|0)9\d{9}$/'],
                'email' => 'required|email|max:255',
                default => ['required', 'string', 'regex:/^[0-9 \-]{6,30}$/'],
            },
        ], collect($requirements)->mapWithKeys(fn ($doc, $field) => [$field => "required|{$documentRule}"])->all()), [
            'org_choice.required' => 'Choose your organization, or "Others" to type its name.',
            'org_name_other.required_if' => 'Type the name of your organization.',
            'phone.regex' => 'Enter a mobile number like 09171234567.',
            'categories.required' => 'Choose at least one kind of help you need.',
            'categories.*.in' => 'Choose from the listed kinds of help.',
            'beneficiary_count.min' => "A request covers at least {$min} people.",
            'beneficiary_count.max' => "A request covers at most {$max} people.",
            'transfer_mode.required' => 'Choose how to receive the funds: bank or e-wallet.',
            'provider.required' => 'Choose the bank or e-wallet.',
            'provider.in' => 'Choose the bank or e-wallet from the list.',
            'account_number.regex' => $accountKind === 'mobile' ? 'Enter the e-wallet mobile number, like 09171234567.' : 'Enter the account number (6 to 30 digits).',
            'account_number.email' => 'Enter the email address of the PayPal account.',
        ], [
            'org_choice' => 'organization',
            'org_name_other' => 'organization name',
            'contact_person' => 'contact person',
            'contact_email' => 'contact email',
            'phone' => 'contact number',
            'street' => 'street / house no.',
            'barangay' => 'barangay',
            'municipality' => 'municipality / city',
            'purpose' => 'purpose of the request',
            'beneficiary_count' => 'number of people',
            'account_name' => 'account name',
            'account_number' => $accountKind === 'mobile' ? 'e-wallet number' : ($accountKind === 'email' ? 'PayPal email' : 'account number'),
        ] + collect($requirements)->mapWithKeys(fn ($doc, $field) => [$field => strtolower($doc['label'])])->all());

        $orgName = $validated['org_choice'] === 'other' ? trim($validated['org_name_other']) : $validated['org_choice'];
        if (! $this->rules->accountNameMatches($validated['account_name'], [$orgName, $validated['contact_person']])) {
            throw ValidationException::withMessages([
                'account_name' => 'Funds are only sent to an account in the receiver\'s name. The account name must match the organization name or the contact person exactly.',
            ]);
        }

        $documents = [];
        foreach (array_keys($requirements) as $field) {
            $documents[$field] = $request->file($field)->store('fund_documents/' . $field, config('funding.files_disk'));
        }

        // The first need chosen is the main category (scores, priorities); every need is kept.
        $chosen = array_values(array_intersect($categories, $validated['categories']));
        $primary = $chosen[0];
        $address = ['street' => trim($validated['street']), 'barangay' => trim($validated['barangay']), 'municipality' => trim($validated['municipality'])];
        $created = $this->fundingService->createRequest([
            'user_id' => Auth::id(),
            'status_id' => 1,
            'status_name' => 'pending',
            'category' => $primary,
            'category_name' => $primary,
            'categories' => $chosen,
            'request_type' => 'assistance',
            'org_name' => $orgName,
            'contact_person' => $validated['contact_person'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['phone'],
            'address' => implode(', ', $address),
            'address_parts' => $address,
            'tax_id' => $validated['tax_id'] ?? null,
            'purpose' => $validated['purpose'],
            'mission' => $validated['purpose'],
            // The names of the people are listed when the funds are released.
            'beneficiaries' => [],
            'beneficiary_count' => (int) $validated['beneficiary_count'],
            // The per-person budget (and so the amount) is decided by an admin after the assessment.
            'amount_requested' => 0,
            'documents' => $documents,
            'disbursement' => [
                'method' => config("funding.transfer_modes.{$mode}.method"),
                'mode' => $mode,
                'provider' => $validated['provider'],
                // Kept for the pages that show "bank · name · number".
                'bank_name' => $validated['provider'],
                'account_name' => $validated['account_name'],
                'account_number' => $accountKind === 'email' ? trim($validated['account_number']) : preg_replace('/[\s\-]/', '', $validated['account_number']),
            ],
        ]);

        // Score criticality with the AI providers once the response has been sent, so the user never waits.
        $requestId = $created['id'];
        dispatch(fn () => app(AiScoringService::class)->scoreAndStore($requestId))->afterResponse();
        $this->notifications->notifyAdmins($requestId, 'new_request', 'New funding request',
            "{$orgName} (" . $this->rules->categoryLabels($chosen) . ") asks help for {$validated['beneficiary_count']} people. Schedule the social worker interview within " . config('funding.assessment_days', 7) . ' days.');

        $days = (int) config('funding.assessment_days', 7);

        return redirect()->route('fund-request.show', $requestId)
            ->with('success', "Your request was submitted and is now under assessment. A social worker will contact you for an interview within {$days} days.");
    }

    /**
     * After approval, the requester lists the people who will receive the help (Add person:
     * last, first and middle name). The funds are released once the list is in.
     */
    public function submitBeneficiaries(Request $request, string $id)
    {
        $fundRequest = $this->fundingService->getRequestById($id);
        abort_if($fundRequest === null || (string) ($fundRequest['user_id'] ?? '') !== (string) Auth::id(), 404);

        $limit = $this->fundingService->approvedPeople($fundRequest);
        $request->validate([
            'people' => 'required|array|min:1|max:' . max(1, $limit),
            'people.*.last' => 'required|string|max:80',
            'people.*.first' => 'required|string|max:80',
            'people.*.middle' => 'nullable|string|max:80',
        ], [
            'people.required' => 'Add the people who will receive the help.',
            'people.max' => "The approved budget covers {$limit} people. Remove the extra names.",
            'people.*.last.required' => 'Enter the last name of person #:position.',
            'people.*.first.required' => 'Enter the first name of person #:position.',
        ]);

        $people = $this->rules->cleanPeople($request->input('people', []));
        $updated = $this->fundingService->submitBeneficiaries($id, $people);
        if ($updated === null) {
            return back()->with('alert_error', 'The list of people can only be sent after approval and before the funds are released.');
        }

        $this->notifications->notifyAdmins($id, 'beneficiaries_submitted', 'List of people received',
            ($fundRequest['org_name'] ?? 'The requester') . ' listed ' . count($people) . ' people. The request is ready for the release of funds.');
        $this->notifications->notifySuperAdmins($id, 'beneficiaries_submitted', 'Ready for the transfer',
            ($fundRequest['org_name'] ?? 'A request') . ' listed ' . count($people) . ' people and can now receive the approved funds.');

        return redirect()->to(route('fund-request.show', $id) . '#release')
            ->with('success', 'Thank you! The foundation will now release the funds to your account.');
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
            'recipients' => 'nullable|array|max:100',
            'recipients.*.last' => 'nullable|string|max:80',
            'recipients.*.first' => 'nullable|string|max:80',
            'recipients.*.middle' => 'nullable|string|max:80',
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
            'recipient_names' => array_map(fn (array $person) => $this->rules->personName($person), $this->rules->cleanPeople($validated['recipients'] ?? [])),
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
