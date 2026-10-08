<?php

namespace App\Services;

use App\Repositories\FirebaseUserRepository;
use Carbon\Carbon;

/**
 * Turns a Firebase funding request into what the user and admin detail pages display.
 */
class FundingRequestPresenter
{
    private const LEGACY_DOCUMENTS = [
        'doc_image' => 'Supporting document',
        'id_image' => 'Valid ID',
        'financial_rprt' => 'Financial report',
        'barangay_clr' => 'Barangay clearance',
    ];

    public const STEPS = ['Submitted', 'Interview & assessment', 'Admin review', 'Final approval', 'Funds released', 'Liquidation', 'Completed'];

    private array $userNames = [];

    private string $requestId = '';

    public function __construct(
        private FundingService $funding,
        private FundingRules $rules,
        private FirebaseUserRepository $users
    ) {
    }

    /**
     * @param  bool  $forAdmin  Admin views get everything. The requester's view leaves out internal
     *                          data: AI scoring, admin notes and verification notes, the social
     *                          worker's findings and the full bank account number.
     */
    public function present(array $request, bool $forAdmin = false): array
    {
        $this->requestId = (string) $request['id'];
        $view = $this->build($request, $forAdmin);

        if (! $forAdmin) {
            $assessment = $view['assessment'];
            $view['assessment'] = array_intersect_key($assessment, array_flip(['interview_at', 'mode', 'mode_label', 'location', 'social_worker', 'interview_response']))
                + ['assessed' => ! empty($assessment['assessed_at'])];
            $view['ai'] = null;
            $view['admin_review'] = null;
            $view['final_notes'] = null;
            $view['staff_notes'] = [];
        }

        return $view;
    }

    /**
     * Reason shown to the requester when a request is rejected: the super admin's note, or the
     * admin's rejection reason. An admin's internal note on an approval is never shown.
     */
    public function rejectionReason(array $request): ?string
    {
        if (($request['status_name'] ?? $request['status'] ?? '') !== 'rejected') {
            return null;
        }

        return ($request['final_notes'] ?? null)
            ?: (($request['admin_decision'] ?? null) === 'rejected' ? ($request['admin_notes'] ?? null) : null);
    }

    private function build(array $request, bool $forAdmin): array
    {
        $category = $request['category_name'] ?? $request['category'] ?? 'General';
        $stage = $this->funding->stageOf($request);
        $budget = (array) ($request['budget'] ?? []);
        $disbursement = (array) ($request['disbursement'] ?? []);
        $assessmentDue = $this->funding->assessmentDueAt($request);
        $liquidationDue = $this->funding->liquidationDueAt($request);

        return [
            'id' => (string) $request['id'],
            'organization' => $request['org_name'] ?? '—',
            'category' => $category,
            'status' => $this->funding->statusOf($request),
            'stage' => $stage,
            'purpose' => $request['purpose'] ?? $request['mission'] ?? '',
            'amount' => (float) ($request['amount_requested'] ?? $request['amount'] ?? 0),
            'granted' => isset($request['approved_amount']) ? (float) $request['approved_amount'] : null,
            'contact' => [
                'Contact person' => $request['contact_person'] ?? null,
                'Email' => $request['contact_email'] ?? null,
                'Phone' => $request['contact_phone'] ?? $request['phone'] ?? null,
                'Address' => $request['address'] ?? null,
                'Tax ID' => $request['tax_id'] ?? null,
            ],
            'beneficiaries' => array_values((array) ($request['beneficiaries'] ?? [])),
            'beneficiary_count' => (int) ($request['beneficiary_count'] ?? count((array) ($request['beneficiaries'] ?? []))),
            'budget' => [
                'per_person' => isset($budget['per_person']) ? (float) $budget['per_person'] : null,
                'people' => isset($budget['people']) ? (int) $budget['people'] : null,
                'items' => array_map(fn (array $item): array => $item + ['check' => $this->rules->priceCheck($item)], (array) ($budget['items'] ?? [])),
            ],
            'per_person_cap' => $this->rules->perPersonCap($category),
            'documents' => $this->documents($request),
            'documents_verified' => $this->funding->documentsVerified($request),
            'interview_response' => $request['assessment']['interview_response'] ?? null,
            'disbursement' => $this->disbursement($disbursement, $forAdmin),
            'assessment' => $this->assessment((array) ($request['assessment'] ?? [])),
            'assessment_due' => $assessmentDue,
            'assessment_overdue' => $stage['step'] === 1 && $assessmentDue?->isPast(),
            'liquidation' => $this->liquidation((array) ($request['liquidation'] ?? [])),
            'liquidation_due' => $liquidationDue,
            'liquidation_overdue' => $stage['key'] === 'released' && $liquidationDue?->isPast(),
            'ai' => isset($request['ai_score']) ? [
                'score' => (float) $request['ai_score'],
                'breakdown' => (array) ($request['ai_breakdown'] ?? []),
                'recommendation' => $request['ai_recommendation'] ?? null,
                'reasoning' => $request['ai_reasoning'] ?? null,
                'red_flags' => (array) ($request['ai_red_flags'] ?? []),
                'provider' => $request['ai_provider'] ?? null,
                'scored_at' => $request['ai_scored_at'] ?? null,
            ] : null,
            'admin_review' => ! empty($request['admin_reviewed_at']) ? [
                'decision' => $request['admin_decision'] ?? null,
                'amount' => isset($request['admin_recommended_amount']) ? (float) $request['admin_recommended_amount'] : null,
                'ai_amount' => isset($request['ai_recommended_amount']) ? (float) $request['ai_recommended_amount'] : null,
                'notes' => $request['admin_notes'] ?? null,
                'by' => $this->nameOf($request['admin_reviewed_by'] ?? null),
                'at' => $request['admin_reviewed_at'],
            ] : null,
            'final_notes' => $request['final_notes'] ?? null,
            'staff_notes' => $forAdmin ? $this->funding->staffNotesOf($request) : [],
            'rejection_reason' => $this->rejectionReason($request),
            'timeline' => $this->timeline($request, $forAdmin),
            'created_at' => $request['created_at'] ?? null,
        ];
    }

    private function documents(array $request): array
    {
        $documents = [];
        $stored = (array) ($request['documents'] ?? []);
        $requirements = $this->rules->requirementsFor($request['category_name'] ?? $request['category'] ?? null);

        $statuses = $this->funding->documentStatuses($request);
        foreach ($requirements as $field => $requirement) {
            $review = (array) ($request['document_reviews'][$field] ?? []);
            $documents[] = $this->file($requirement['label'], $stored[$field] ?? null, true) + [
                'field' => $field,
                'hint' => $requirement['hint'] ?? null,
                'status' => $statuses[$field] ?? 'missing',
                'remarks' => $review['remarks'] ?? null,
                'reviewed_at' => $review['reviewed_at'] ?? null,
            ];
        }
        foreach ($stored as $field => $path) {
            if (! isset($requirements[$field])) {
                $documents[] = $this->file(ucfirst(str_replace('_', ' ', $field)), $path, false);
            }
        }
        foreach (self::LEGACY_DOCUMENTS as $field => $label) {
            if (! empty($request[$field])) {
                $documents[] = $this->file($label, $request[$field], false);
            }
        }

        return $documents;
    }

    public function file(string $label, ?string $path, bool $required = false): array
    {
        $extension = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : null;

        return [
            'label' => $label,
            // Served by FundingFileController, which checks the viewer may see this request.
            'url' => $path ? route('fund-request.file', ['id' => $this->requestId, 'path' => ltrim($path, '/')]) : null,
            'extension' => $extension,
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
            'required' => $required,
        ];
    }

    private function disbursement(array $disbursement, bool $forAdmin): array
    {
        $method = $disbursement['method'] ?? null;

        return [
            'method' => $method,
            'method_label' => config("funding.disbursement_methods.{$method}") ?? '—',
            'bank_name' => $disbursement['bank_name'] ?? null,
            'account_name' => $disbursement['account_name'] ?? null,
            'account_number' => $forAdmin
                ? ($disbursement['account_number'] ?? null)
                : $this->rules->maskAccountNumber($disbursement['account_number'] ?? null),
            'released_at' => $disbursement['released_at'] ?? null,
            'amount' => isset($disbursement['amount']) ? (float) $disbursement['amount'] : null,
            'reference_no' => $disbursement['reference_no'] ?? null,
            'notes' => $disbursement['notes'] ?? null,
            'proof' => ! empty($disbursement['proof']) ? $this->file('Proof of release', $disbursement['proof']) : null,
            'recorded_by' => $this->nameOf($disbursement['recorded_by'] ?? null),
        ];
    }

    private function assessment(array $assessment): array
    {
        return $assessment + [
            'mode_label' => config('funding.assessment_modes.' . ($assessment['mode'] ?? '')) ?? null,
            'scheduled_by_name' => $this->nameOf($assessment['scheduled_by'] ?? null),
            'assessed_by_name' => $this->nameOf($assessment['assessed_by'] ?? null),
        ];
    }

    private function liquidation(array $liquidation): array
    {
        if ($liquidation === []) {
            return [];
        }

        return array_merge($liquidation, [
            'receipt_files' => array_map(fn (string $path) => $this->file('Receipt', $path), (array) ($liquidation['receipts'] ?? [])),
            'photo_files' => array_map(fn (string $path) => $this->file('Photo', $path), (array) ($liquidation['photos'] ?? [])),
            'recipient_file' => ! empty($liquidation['recipient_list']) ? $this->file('List of recipients', $liquidation['recipient_list']) : null,
            'reviewed_by_name' => $this->nameOf($liquidation['reviewed_by'] ?? null),
        ]);
    }

    /**
     * Dated events for the request history.
     */
    private function timeline(array $request, bool $forAdmin): array
    {
        $assessment = (array) ($request['assessment'] ?? []);
        $disbursement = (array) ($request['disbursement'] ?? []);
        $liquidation = (array) ($request['liquidation'] ?? []);
        $mode = config('funding.assessment_modes.' . ($assessment['mode'] ?? ''));

        $events = [
            [$request['created_at'] ?? null, 'Request submitted', null],
            [$assessment['scheduled_at'] ?? null, 'Interview scheduled', isset($assessment['interview_at']) ? Carbon::parse($assessment['interview_at'])->format('M d, Y g:i A') . ($mode ? " · {$mode}" : '') . (! empty($assessment['social_worker']) ? " · {$assessment['social_worker']}" : '') : null],
            [$assessment['assessed_at'] ?? null, 'Assessment completed' . ($forAdmin ? ': ' . (($assessment['outcome'] ?? '') === 'recommended' ? 'recommended' : 'not recommended') : ''), null],
            [$request['admin_reviewed_at'] ?? null, $forAdmin ? 'Reviewed by admin' : 'Reviewed by the foundation', null],
            [$request['approved_at'] ?? null, 'Approved' . (isset($request['approved_amount']) ? ' · ₱' . number_format((float) $request['approved_amount'], 2) : ''), null],
            [$request['rejected_at'] ?? null, 'Rejected', $forAdmin ? ($request['final_notes'] ?? $request['admin_notes'] ?? null) : $this->rejectionReason($request)],
            [$disbursement['recorded_at'] ?? null, 'Funds released', isset($disbursement['amount']) ? '₱' . number_format((float) $disbursement['amount'], 2) . ' · ' . (config('funding.disbursement_methods.' . ($disbursement['method'] ?? '')) ?? '') : null],
        ];
        foreach ((array) ($request['appeal_history'] ?? []) as $appeal) {
            $events[] = [$appeal['previous_decision_at'] ?? null, 'Rejected', $appeal['previous_reason'] ?? null];
            $events[] = [$appeal['at'] ?? null, 'Appeal ' . ($appeal['number'] ?? '') . ' submitted', $appeal['reason'] ?? null];
        }
        foreach ((array) ($liquidation['history'] ?? []) as $previous) {
            $events[] = [$previous['submitted_at'] ?? null, 'Liquidation submitted', null];
            $events[] = [$previous['reviewed_at'] ?? null, 'Liquidation returned for changes', $previous['review_remarks'] ?? null];
        }
        $events[] = [$liquidation['submitted_at'] ?? null, 'Liquidation submitted', null];
        $events[] = [($liquidation['status'] ?? '') !== 'submitted' ? ($liquidation['reviewed_at'] ?? null) : null,
            ($liquidation['status'] ?? '') === 'verified' ? 'Liquidation verified · request completed' : 'Liquidation returned for changes',
            $liquidation['review_remarks'] ?? null];

        $events = array_values(array_filter($events, static fn (array $event): bool => ! empty($event[0])));
        usort($events, static fn (array $first, array $second): int => strcmp((string) $first[0], (string) $second[0]));

        return array_map(static fn (array $event): array => ['at' => $event[0], 'label' => $event[1], 'detail' => $event[2]], $events);
    }

    private function nameOf(?string $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        if (! array_key_exists($userId, $this->userNames)) {
            $user = $this->users->findById($userId) ?? [];
            $this->userNames[$userId] = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? '')) ?: null;
        }

        return $this->userNames[$userId];
    }
}
