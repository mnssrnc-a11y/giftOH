<?php

namespace App\Http\Controllers;

use App\Services\FundingRules;
use App\Services\FundingService;
use App\Services\NotificationService;
use App\Services\RequestMessageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Requester ↔ foundation interactions on one funding request: the message thread (both sides),
 * the requester's answer to an interview schedule, and document resubmission.
 */
class RequestConversationController extends Controller
{
    public function __construct(
        private FundingService $funding,
        private RequestMessageService $messages,
        private NotificationService $notifications
    ) {
    }

    /**
     * Thread HTML for the viewer's side (used for live refresh). Opening it marks messages as read.
     */
    public function index(string $id)
    {
        [$request, $side] = $this->authorizeRequest($id);
        $thread = $this->messages->thread($id, $side);
        $this->messages->markRead($id, $side);

        return view($side === 'staff' ? 'adminPage.partials.messages' : 'FundPage.partials.messages', ['thread' => $thread, 'side' => $side]);
    }

    public function store(Request $httpRequest, string $id)
    {
        [$request] = $this->authorizeRequest($id);
        $validated = $httpRequest->validate(['body' => 'required|string|max:2000']);

        $this->messages->send($request, Auth::user(), trim($validated['body']));

        return $httpRequest->expectsJson()
            ? response()->json(['ok' => true])
            : back()->withFragment('messages')->with('status', 'Message sent.');
    }

    /**
     * Requester confirms the interview or asks for another schedule.
     */
    public function interviewResponse(Request $httpRequest, string $id)
    {
        [$request, $side] = $this->authorizeRequest($id);
        abort_unless($side === 'requester', 403);

        $validated = $httpRequest->validate([
            'response' => 'required|in:confirmed,reschedule_requested',
            'preferred_at' => 'required_if:response,reschedule_requested|nullable|date|after:now',
            'note' => 'required_if:response,reschedule_requested|nullable|string|max:1000',
        ], [
            'preferred_at.required_if' => 'Choose a date and time that works for you.',
            'note.required_if' => 'Tell the social worker why the schedule does not work.',
        ]);

        $updated = $this->funding->respondToInterview($id, [
            'status' => $validated['response'],
            'preferred_at' => $validated['preferred_at'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);
        if ($updated === null) {
            return back()->with('alert_error', 'There is no interview waiting for your answer.');
        }

        $this->messages->system($id, $validated['response'] === 'confirmed'
            ? 'Requester confirmed the interview schedule.'
            : 'Requester asked for another interview schedule: ' . Carbon::parse($validated['preferred_at'])->format('M d, Y g:i A') . " — “{$validated['note']}”", 'staff');
        $this->notifications->notifyAdmins($id, 'interview_response', $validated['response'] === 'confirmed' ? 'Interview confirmed' : 'Interview: another schedule asked',
            ($request['org_name'] ?? 'The requester') . ($validated['response'] === 'confirmed'
                ? ' will attend the interview.'
                : ' asks for ' . Carbon::parse($validated['preferred_at'])->format('M d, Y g:i A') . '. Reschedule the interview.'));

        return redirect()->route('fund-request.show', $id)->with('success', $validated['response'] === 'confirmed'
            ? 'Thank you! The foundation has been told you will attend the interview.'
            : 'Your request for another schedule was sent. The social worker will set a new date.');
    }

    /**
     * Requester uploads a document that is missing or was marked for resubmission.
     */
    public function resubmitDocument(Request $httpRequest, string $id, string $field, FundingRules $rules)
    {
        [$request, $side] = $this->authorizeRequest($id);
        abort_unless($side === 'requester', 403);
        $requirements = $rules->requirementsFor($request['category_name'] ?? $request['category'] ?? null);
        abort_unless(isset($requirements[$field]), 404);

        $httpRequest->validate(['file' => 'required|' . config('funding.document_rules')]);
        $path = $httpRequest->file('file')->store("fund_documents/{$field}", config('funding.files_disk'));

        $previous = $this->funding->replaceDocument($id, $field, $path);
        if ($previous === false) {
            Storage::disk(config('funding.files_disk'))->delete($path);

            return back()->with('alert_error', 'This document cannot be replaced right now.');
        }
        if ($previous) {
            Storage::disk(config('funding.files_disk'))->delete($previous);
        }

        $this->messages->system($id, "Requester uploaded a new {$requirements[$field]['label']} for review.", 'staff');
        $this->notifications->notifyAdmins($id, 'document_uploaded', 'New document to review',
            ($request['org_name'] ?? 'A requester') . " uploaded a new {$requirements[$field]['label']}.");

        return redirect()->to(route('fund-request.show', $id) . '#documents')->with('success', "{$requirements[$field]['label']} uploaded. The foundation will review it.");
    }

    /**
     * @return array{0: array, 1: string} the request and the viewer's side (requester|staff)
     */
    private function authorizeRequest(string $id): array
    {
        $request = $this->funding->getRequestById($id);
        abort_if($request === null, 404);

        $user = Auth::user();
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return [$request, 'staff'];
        }
        abort_unless((string) ($request['user_id'] ?? '') === (string) $user->getAuthIdentifier(), 404);

        return [$request, 'requester'];
    }
}
