<?php

namespace App\Http\Controllers;

use App\Services\AiScoringService;
use App\Services\AuditLogger;
use App\Services\FundingRequestPresenter;
use App\Services\NotificationService;
use App\Services\RequestMessageService;
use App\Services\FundingRules;
use App\Services\FundingService;
use App\Services\IotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin processing of a single funding request: social worker interview and assessment,
 * document verification, verification notes, the per-person budget (items from the super
 * admin's price list), AI scoring, release of funds, and liquidation review.
 */
class AdminRequestController extends Controller
{
    public function __construct(
        private FundingService $funding,
        private FundingRequestPresenter $presenter,
        private RequestMessageService $messages,
        private NotificationService $notifications,
        private AuditLogger $audit
    ) {
    }

    public function show(string $id, IotService $iot)
    {
        $request = $this->funding->getRequestById($id);
        abort_if($request === null, 404);

        $view = $this->presenter->present($request, forAdmin: true);
        $pendingCount = count($this->funding->getPendingRequests());
        $availableFunds = $iot->getAvailableFunds();
        $thread = $this->messages->thread($id, 'staff');
        $this->messages->markRead($id, 'staff');

        return view('adminPage.request-show', compact('view', 'pendingCount', 'availableFunds', 'thread'));
    }

    public function scheduleInterview(Request $request, string $id)
    {
        $validated = $request->validate([
            'interview_at' => 'required|date|after_or_equal:today',
            'social_worker' => 'required|string|max:120',
            'mode' => ['required', Rule::in(array_keys(config('funding.assessment_modes')))],
            'location' => 'nullable|string|max:255',
        ]);
        $validated['mode_label'] = config("funding.assessment_modes.{$validated['mode']}");

        $updated = $this->funding->scheduleInterview($id, $validated, Auth::id());
        if ($updated) {
            $this->messages->system($id, 'Interview scheduled: ' . \Carbon\Carbon::parse($validated['interview_at'])->format('M d, Y g:i A')
                . " · {$validated['mode_label']}" . (! empty($validated['location']) ? " · {$validated['location']}" : '')
                . " · {$validated['social_worker']}. Please confirm or ask for another schedule.", 'requester');
        }

        return $this->back($id, $updated, 'Interview scheduled. The requester has been notified.');
    }

    /**
     * Verify a submitted document, or ask the requester to resubmit it with remarks.
     */
    public function reviewDocument(Request $request, string $id, string $field, FundingRules $rules)
    {
        $fundRequest = $this->funding->getRequestById($id);
        abort_if($fundRequest === null, 404);
        $requirements = $rules->requirementsFor($fundRequest['category_name'] ?? $fundRequest['category'] ?? null);
        abort_unless(isset($requirements[$field]), 404);

        $validated = $request->validate([
            'decision' => 'required|in:verified,resubmit',
            'remarks' => 'required_if:decision,resubmit|nullable|string|max:1000',
        ], ['remarks.required_if' => 'Tell the requester what is wrong with the document.']);

        $verified = $validated['decision'] === 'verified';
        $updated = $this->funding->reviewDocument($id, $field, $verified, $validated['remarks'] ?? null, Auth::id());
        $label = $requirements[$field]['label'];
        if ($updated && ! $verified) {
            $this->messages->system($id, "Please upload a new {$label}: {$validated['remarks']}", 'requester');
            $this->notifications->notify($fundRequest['user_id'] ?? '', $id, 'document_resubmit', 'Document needs to be resubmitted',
                "Your {$label} for " . ($fundRequest['org_name'] ?? 'your request') . " needs a new upload: {$validated['remarks']}");
        }
        if ($updated) {
            $this->audit->record('funding', ($verified ? 'Verified ' : 'Asked to resubmit ') . $label, $id, ['organization' => $fundRequest['org_name'] ?? null]);
        }

        return redirect()->to(route('admin.fund-request.show', $id) . '#documents')->with(
            $updated ? 'status' : 'alert_error',
            $updated ? ($verified ? "{$label} verified." : "The requester was asked to resubmit the {$label}.") : 'Documents can only be reviewed while the request is pending.'
        );
    }

    public function recordAssessment(Request $request, string $id)
    {
        $validated = $request->validate([
            'outcome' => 'required|in:recommended,not_recommended',
            'verified_beneficiaries' => 'required|integer|min:0|max:' . config('funding.beneficiaries.max'),
            'notes' => 'required|string|max:5000',
            'social_worker' => 'nullable|string|max:120',
        ], [
            'notes.required' => 'Summarize the social worker\'s findings.',
        ]);

        $updated = $this->funding->recordAssessment($id, $validated, Auth::id());
        if ($updated) {
            $this->audit->record('funding', 'Assessment recorded: ' . str_replace('_', ' ', $validated['outcome']), $id, ['verified_people' => $validated['verified_beneficiaries']]);
        }

        return $this->back($id, $updated, $validated['outcome'] === 'recommended'
            ? 'Assessment recorded. The request is ready for your decision.'
            : 'Assessment recorded as not recommended. You can now reject the request.');
    }

    /**
     * Admin decides the budget per person: items and quantities from the super admin's price list.
     * Prices always come from the list, never from the form, and admins cannot add items.
     */
    public function setBudget(Request $request, string $id, FundingRules $rules)
    {
        $fundRequest = $this->funding->getRequestById($id);
        abort_if($fundRequest === null, 404);

        $validated = $request->validate([
            'budget' => 'required|array|min:1|max:40',
            'budget.*.ref' => 'required|string|max:120',
            'budget.*.qty' => 'required|numeric|min:0.25|max:10000',
        ], [
            'budget.required' => 'Add at least one item to the per-person budget.',
            'budget.*.ref.required' => 'Items must come from the price list.',
        ]);
        $budget = $rules->buildBudget($validated['budget']);

        if ($budget['items'] === []) {
            return redirect()->route('admin.fund-request.show', $id)->with('alert_error', 'Add at least one item from the price list with a quantity.');
        }

        $updated = $this->funding->setBudget($id, $budget, Auth::id());
        if ($updated) {
            $this->audit->record('funding', 'Budget set: ₱' . number_format($budget['per_person'], 2) . ' per person', $id, ['organization' => $fundRequest['org_name'] ?? null]);
        }

        return $this->back($id, $updated, 'Budget saved: ₱' . number_format($budget['per_person'], 2) . ' per person.');
    }

    /**
     * Admin verification notes on a request. Staff only: the super admin sees them when finalizing,
     * the requester never does.
     */
    public function addNote(Request $request, string $id)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ], ['note.required' => 'Write the note first.']);

        $fundRequest = $this->funding->getRequestById($id);
        abort_if($fundRequest === null, 404);

        $note = $this->funding->addStaffNote($id, trim($validated['note']), Auth::user());
        if ($note) {
            $this->audit->record('funding', 'Verification note added', $id, ['organization' => $fundRequest['org_name'] ?? null]);
        }

        return redirect()->to(route('admin.fund-request.show', $id) . '#notes')->with(
            $note ? 'status' : 'alert_error',
            $note ? 'Note added. The super admin will see it when finalizing.' : 'The note could not be saved. Please try again.'
        );
    }

    public function rescore(string $id, AiScoringService $scoring)
    {
        $result = $scoring->scoreAndStore($id);

        return redirect()->to(route('admin.fund-request.show', $id) . '#ai')->with(
            $result ? 'status' : 'alert_error',
            $result ? "AI assessment updated using {$result['ai_provider']}." : 'No AI provider is available right now. Add an API key in .env or try again later.'
        );
    }

    public function recordDisbursement(Request $request, string $id)
    {
        $fundRequest = $this->funding->getRequestById($id);
        abort_if($fundRequest === null, 404);
        $granted = (float) ($fundRequest['approved_amount'] ?? $fundRequest['amount_requested'] ?? 0);

        $validated = $request->validate([
            'method' => ['required', Rule::in(array_keys(config('funding.disbursement_methods')))],
            'amount' => "required|numeric|min:1|max:{$granted}",
            'released_at' => 'required|date|before_or_equal:today',
            'reference_no' => 'required|string|max:120',
            'notes' => 'nullable|string|max:2000',
            'proof' => 'required|' . config('funding.document_rules'),
        ], [
            'amount.max' => 'The released amount cannot exceed the approved amount.',
            'reference_no.required' => 'Enter the bank transfer reference number.',
            'proof.required' => 'Upload the bank transfer slip.',
        ]);

        $validated['proof'] = $request->file('proof')->store("disbursements/{$id}", config('funding.files_disk'));
        $validated['amount'] = round((float) $validated['amount'], 2);

        $updated = $this->funding->recordDisbursement($id, $validated, Auth::id());
        if (! $updated) {
            // Nothing was recorded, so do not keep the uploaded slip.
            Storage::disk(config('funding.files_disk'))->delete($validated['proof']);
        }
        if ($updated) {
            $this->audit->record('funding', 'Funds released: ₱' . number_format($validated['amount'], 2), $id, ['method' => $validated['method'], 'reference' => $validated['reference_no'] ?? null]);
        }

        return $this->back($id, $updated, 'Release recorded. The requester was asked to submit the liquidation.');
    }

    public function reviewLiquidation(Request $request, string $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:verified,returned',
            'remarks' => 'required_if:decision,returned|nullable|string|max:2000',
        ], [
            'remarks.required_if' => 'Explain what needs to be corrected.',
        ]);

        $updated = $this->funding->reviewLiquidation($id, $validated['decision'] === 'verified', $validated['remarks'] ?? null, Auth::id());
        if ($updated) {
            $this->audit->record('funding', $validated['decision'] === 'verified' ? 'Liquidation verified' : 'Liquidation returned', $id);
        }

        return $this->back($id, $updated, $validated['decision'] === 'verified'
            ? 'Liquidation verified. The request is now completed.'
            : 'Liquidation returned to the requester with your remarks.');
    }

    private function back(string $id, ?array $updated, string $message)
    {
        return redirect()->route('admin.fund-request.show', $id)->with(
            $updated ? 'status' : 'alert_error',
            $updated ? $message : 'That step is not available for this request at its current stage.'
        );
    }
}
