<?php

namespace App\Services;

use App\Repositories\FirebaseAdminPostRepository;
use App\Repositories\FirebaseDonationRepository;
use App\Repositories\FirebaseUserRepository;
use Carbon\Carbon;

/**
 * Builds every data-backed card on the admin workspace from Firebase records.
 * Cards whose source data is empty receive empty arrays so the view can show an empty state
 * instead of placeholder numbers.
 */
class AdminDashboardService
{
    private const CATEGORY_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4'];

    private array $userCache = [];

    public function __construct(
        private FundingService $funding,
        private FirebaseUserRepository $users,
        private FirebaseDonationRepository $donations,
        private FirebaseAdminPostRepository $posts
    ) {
    }

    public function build(array $iotMetrics): array
    {
        $requests = $this->fundingRows();
        $donations = $this->donationRows();
        $posts = $this->postRows();
        $totalFunds = $iotMetrics['totalFunds'] ?? null;

        return [
            'fundingRequests' => $requests,
            'posts' => $posts,
            'donationSeries' => $this->monthlySeries($donations, 'date', 'amount'),
            'fundingSeries' => [
                'requested' => $this->monthlySeries($requests, 'date', 'amount'),
                'granted' => $this->monthlySeries(
                    array_filter($requests, fn (array $row): bool => in_array($row['status'], ['approved', 'completed'], true)),
                    'decided_at',
                    'granted'
                ),
            ],
            'transactions' => array_slice($donations, 0, 5),
            'donationRecordCount' => count($donations),
            'allocation' => $this->allocation($requests, $totalFunds),
            'activities' => array_slice($this->activities($requests, $posts), 0, 6),
            'fundingSummary' => $this->fundingSummary($requests),
            'fundingReport' => $this->fundingReport($requests),
            'reportInsight' => $this->reportInsight($requests, $donations, $iotMetrics),
        ];
    }

    /**
     * Funding requests shaped for the admin table, request modal and reports.
     */
    public function fundingRows(): array
    {
        // Unread requester messages per request (badge in the admin list and notifications).
        $unread = app(RequestMessageService::class)->unreadForStaff();
        $rows = array_map(function (array $request) use ($unread): array {
            $status = $this->funding->statusOf($request);
            $user = $this->user($request['user_id'] ?? null);

            return [
                'id' => (string) ($request['id'] ?? ''),
                'project' => $request['title'] ?? $request['org_name'] ?? 'Funding request',
                'organization' => $request['org_name'] ?? '—',
                'category' => $request['category_name'] ?? $request['category'] ?? 'General',
                'requester' => $this->nameOf($user) ?: ($request['contact_person'] ?? 'Unknown requester'),
                'contact' => array_filter([
                    'person' => $request['contact_person'] ?? null,
                    'email' => $request['contact_email'] ?? null,
                    'phone' => $request['contact_phone'] ?? null,
                    'address' => $request['address'] ?? null,
                ]),
                'amount' => (float) ($request['amount_requested'] ?? $request['amount'] ?? 0),
                'granted' => in_array($status, ['approved', 'completed'], true) ? $this->funding->grantedAmountOf($request) : null,
                'status' => $status === FundingService::STATUS_UNDER_REVIEW ? 'awaiting' : $status,
                'date' => $request['created_at'] ?? $request['updated_at'] ?? '',
                'decided_at' => $request['approved_at'] ?? $request['rejected_at'] ?? null,
                'description' => $request['description'] ?? $request['mission'] ?? '',
                'reason' => $request['reason'] ?? $request['mission'] ?? '',
                'ai_score' => isset($request['ai_score']) ? (float) $request['ai_score'] : null,
                'stage' => $stage = $this->funding->stageOf($request),
                'beneficiary_count' => (int) ($request['beneficiary_count'] ?? 0),
                'per_person' => isset($request['budget']['per_person']) ? (float) $request['budget']['per_person'] : null,
                'assessment_outcome' => $request['assessment']['outcome'] ?? null,
                'verified_beneficiaries' => isset($request['assessment']['verified_beneficiaries']) ? (int) $request['assessment']['verified_beneficiaries'] : null,
                'assessment_notes' => $request['assessment']['notes'] ?? null,
                'assessment_due' => $this->funding->assessmentDueAt($request)?->toIso8601String(),
                'overdue' => ($stage['step'] === 1 && $this->funding->assessmentDueAt($request)?->isPast())
                    || ($stage['key'] === 'released' && $this->funding->liquidationDueAt($request)?->isPast()),
                'admin_decision' => $request['admin_decision'] ?? null,
                'admin_amount' => isset($request['admin_recommended_amount']) ? (float) $request['admin_recommended_amount'] : null,
                'ai_amount' => isset($request['ai_recommended_amount']) ? (float) $request['ai_recommended_amount'] : null,
                'admin_notes' => $request['admin_notes'] ?? null,
                'staff_notes' => $this->funding->staffNotesOf($request),
                'review' => $this->reviewTrail($request),
                'unread_messages' => $unread[(string) ($request['id'] ?? '')]['count'] ?? 0,
                'last_message_at' => $unread[(string) ($request['id'] ?? '')]['last_at'] ?? null,
            ];
        }, $this->funding->getAllRequests());

        usort($rows, static fn (array $first, array $second): int => strcmp($second['date'], $first['date']));

        return $rows;
    }

    private function reviewTrail(array $request): array
    {
        $trail = [];

        if (! empty($request['admin_reviewed_at'])) {
            $trail[] = [
                'label' => 'Admin ' . ($request['admin_decision'] === 'approved' ? 'recommended approval' : 'recommended rejection')
                    . (isset($request['admin_recommended_amount']) ? ' of ₱' . number_format((float) $request['admin_recommended_amount'], 2) : ''),
                'by' => $this->nameOf($this->user($request['admin_reviewed_by'] ?? null)) ?: 'Admin',
                'at' => $request['admin_reviewed_at'],
                'notes' => $request['admin_notes'] ?? null,
            ];
        }

        $decidedAt = $request['approved_at'] ?? $request['rejected_at'] ?? null;
        if ($decidedAt) {
            $status = $this->funding->statusOf($request);
            $trail[] = [
                'label' => ucfirst($status === 'completed' ? 'approved' : $status)
                    . (isset($request['approved_amount']) ? ' · ₱' . number_format((float) $request['approved_amount'], 2) . ' granted' : ''),
                'by' => $this->nameOf($this->user($request['finalized_by'] ?? $request['approved_by'] ?? null)) ?: 'Super admin',
                'at' => $decidedAt,
                'notes' => $request['final_notes'] ?? null,
            ];
        }

        return $trail;
    }

    private function donationRows(): array
    {
        try {
            $records = $this->donations->all();
        } catch (\Throwable) {
            return [];
        }

        $rows = array_map(fn (array $donation): array => [
            'id' => (string) ($donation['id'] ?? ''),
            'amount' => (float) ($donation['amount'] ?? 0),
            'date' => $donation['created_at'] ?? $donation['donated_at'] ?? '',
            'box' => $donation['iot_box_id'] ?? $donation['box_id'] ?? null,
            'type' => $donation['type'] ?? $donation['payment_method'] ?? $donation['method'] ?? 'Donation',
            'status' => strtolower((string) ($donation['status'] ?? 'verified')),
            'donor' => $this->nameOf($this->user($donation['user_id'] ?? null)) ?: ($donation['donor_name'] ?? null),
        ], $records);

        usort($rows, static fn (array $first, array $second): int => strcmp($second['date'], $first['date']));

        return $rows;
    }

    private function postRows(): array
    {
        try {
            $posts = $this->posts->latest();
        } catch (\Throwable) {
            return [];
        }

        return array_map(fn (array $post): array => [
            'id' => (string) $post['id'],
            'title' => $post['title'] ?? 'Update',
            'body' => $post['body'] ?? '',
            'type' => $post['type'] ?? 'announcement',
            'audience' => $post['audience'] ?? FirebaseAdminPostRepository::AUDIENCE_PUBLIC,
            'author' => $this->nameOf($this->user($post['author_id'] ?? null)) ?: 'Admin',
            'author_id' => (string) ($post['author_id'] ?? ''),
            'date' => $post['created_at'] ?? '',
            'image' => FirebaseAdminPostRepository::imageUrl($post),
        ], $posts);
    }

    /**
     * Sum of $amountKey per month for the last six months, oldest first.
     */
    private function monthlySeries(array $rows, string $dateKey, string $amountKey): array
    {
        $months = [];
        for ($offset = 5; $offset >= 0; $offset--) {
            $month = now()->startOfMonth()->subMonths($offset);
            $months[$month->format('Y-m')] = ['label' => $month->format('M'), 'value' => 0.0];
        }

        foreach ($rows as $row) {
            if (empty($row[$dateKey])) {
                continue;
            }
            $key = $this->parse($row[$dateKey])?->format('Y-m');
            if ($key !== null && isset($months[$key])) {
                $months[$key]['value'] += (float) ($row[$amountKey] ?? 0);
            }
        }

        return array_values($months);
    }

    private function allocation(array $requests, ?float $totalFunds): array
    {
        $byCategory = [];
        foreach ($requests as $row) {
            if ($row['granted'] !== null) {
                $byCategory[$row['category']] = ($byCategory[$row['category']] ?? 0) + $row['granted'];
            }
        }
        arsort($byCategory);

        $base = max((float) $totalFunds, array_sum($byCategory));
        $rows = [];
        $index = 0;
        foreach ($byCategory as $category => $amount) {
            $rows[] = [
                'label' => $category,
                'amount' => $amount,
                'percent' => $base > 0 ? round($amount / $base * 100, 1) : 0,
                'color' => self::CATEGORY_COLORS[$index++ % count(self::CATEGORY_COLORS)],
            ];
        }

        return $rows;
    }

    private function activities(array $requests, array $posts): array
    {
        $events = [];

        foreach ($requests as $row) {
            $events[] = ['icon' => '＋', 'title' => 'New request received', 'detail' => "{$row['organization']} · {$row['requester']}", 'at' => $row['date'], 'amount' => $row['amount'], 'status' => null];
            foreach ($row['review'] as $step) {
                $events[] = ['icon' => '✓', 'title' => $step['label'], 'detail' => "{$row['organization']} · by {$step['by']}", 'at' => $step['at'], 'amount' => null, 'status' => null];
            }
        }

        foreach ($posts as $post) {
            $events[] = ['icon' => '✎', 'title' => 'Update posted', 'detail' => "{$post['title']} · {$post['author']}", 'at' => $post['date'], 'amount' => null, 'status' => null];
        }

        $events = array_values(array_filter($events, static fn (array $event): bool => ! empty($event['at'])));
        usort($events, static fn (array $first, array $second): int => strcmp((string) $second['at'], (string) $first['at']));

        return $events;
    }

    private function fundingSummary(array $requests): array
    {
        $sum = static fn (array $rows, string $key = 'amount'): float => array_sum(array_column($rows, $key));
        $byStatus = static fn (string ...$statuses): array => array_filter($requests, static fn (array $row): bool => in_array($row['status'], $statuses, true));

        $pending = $byStatus('pending');
        $awaiting = $byStatus('awaiting');
        $granted = $byStatus('approved', 'completed');
        $decided = count($granted) + count($byStatus('rejected'));

        $thisMonth = array_filter($granted, fn (array $row): bool => $row['decided_at'] && $this->parse($row['decided_at'])?->isSameMonth(now()));

        return [
            'pendingValue' => $sum($pending),
            'pendingCount' => count($pending),
            'awaitingValue' => $sum($awaiting),
            'awaitingCount' => count($awaiting),
            'grantedMonth' => $sum($thisMonth, 'granted'),
            'grantedMonthCount' => count($thisMonth),
            'grantedTotal' => $sum($granted, 'granted'),
            'approvalRate' => $decided > 0 ? round(count($granted) / $decided * 100, 1) : null,
            'decidedCount' => $decided,
            // Process stages from the foundation interview.
            'needsAssessment' => count(array_filter($requests, static fn (array $row): bool => $row['stage']['step'] === 1)),
            'overdue' => count(array_filter($requests, static fn (array $row): bool => $row['overdue'])),
            'toRelease' => count(array_filter($requests, static fn (array $row): bool => $row['stage']['key'] === 'to_release')),
            'liquidationPending' => count(array_filter($requests, static fn (array $row): bool => in_array($row['stage']['key'], ['released', 'liquidation_review', 'liquidation_returned'], true))),
            'liquidationToReview' => count(array_filter($requests, static fn (array $row): bool => $row['stage']['key'] === 'liquidation_review')),
        ];
    }

    private function fundingReport(array $requests): array
    {
        $months = [];
        foreach ($requests as $row) {
            $date = $this->parse($row['date']);
            if (! $date) {
                continue;
            }
            $key = $date->format('Y-m');
            $months[$key] ??= ['month' => $date->format('F Y'), 'date' => $date->startOfMonth()->toDateString(), 'requests' => 0, 'requested' => 0.0, 'granted' => 0.0, 'approved' => 0, 'rejected' => 0];
            $months[$key]['requests']++;
            $months[$key]['requested'] += $row['amount'];
            if ($row['granted'] !== null) {
                $months[$key]['approved']++;
                $months[$key]['granted'] += $row['granted'];
            }
            if ($row['status'] === 'rejected') {
                $months[$key]['rejected']++;
            }
        }
        krsort($months);

        return array_values(array_map(static function (array $month): array {
            $decided = $month['approved'] + $month['rejected'];
            $month['rate'] = $decided > 0 ? round($month['approved'] / $decided * 100) . '%' : '—';

            return $month;
        }, $months));
    }

    private function reportInsight(array $requests, array $donations, array $iotMetrics): array
    {
        $peso = static fn (float $value): string => '₱' . number_format($value, 2);
        $summary = $this->fundingSummary($requests);
        $offline = ($iotMetrics['smartBoxCount'] ?? 0) - ($iotMetrics['onlineBoxCount'] ?? 0);
        $sentences = [];

        if ($iotMetrics['totalFunds'] !== null) {
            $sentences[] = "Total funds stand at {$peso((float) $iotMetrics['totalFunds'])}, with {$peso((float) $iotMetrics['availableFunds'])} still unallocated.";
        }
        if ($summary['pendingCount'] + $summary['awaitingCount'] > 0) {
            $sentences[] = "{$summary['pendingCount']} request(s) worth {$peso($summary['pendingValue'])} await admin review and {$summary['awaitingCount']} await super admin finalization.";
        } else {
            $sentences[] = 'There are no funding requests waiting for a decision.';
        }
        if (($iotMetrics['smartBoxCount'] ?? null) !== null && $offline > 0) {
            $sentences[] = "{$offline} of {$iotMetrics['smartBoxCount']} smart box(es) are offline and may miss the next collection.";
        }

        $headline = match (true) {
            ($iotMetrics['totalFunds'] ?? null) === null => 'Live fund totals are currently unavailable',
            $summary['pendingValue'] > (float) ($iotMetrics['availableFunds'] ?? 0) => 'Pending requests exceed the funds available',
            $summary['pendingCount'] > 0 => 'Available funds can cover the pending queue',
            default => 'Funding queue is clear',
        };

        return [
            'headline' => $headline,
            'body' => implode(' ', $sentences),
            'metrics' => [
                ['label' => 'Donations recorded', 'value' => (string) count($donations)],
                ['label' => 'Approval rate', 'value' => $summary['approvalRate'] !== null ? $summary['approvalRate'] . '%' : '—'],
                ['label' => 'Boxes offline', 'value' => ($iotMetrics['smartBoxCount'] ?? null) !== null ? (string) $offline : '—'],
            ],
        ];
    }

    private function user(string|int|null $id): array
    {
        if ($id === null || $id === '') {
            return [];
        }

        return $this->userCache[(string) $id] ??= ($this->users->findById($id) ?? []);
    }

    private function nameOf(array $user): string
    {
        return (string) ($user['name'] ?? trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? '')));
    }

    private function parse(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
