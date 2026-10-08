<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\FundingService;
use App\Services\FundingRequestPresenter;
use App\Services\FundingRules;
use App\Services\NotificationService;
use App\Services\VerificationService;
use App\Repositories\FirebaseUserRepository;
use App\Repositories\FirebaseAdminPostRepository;
class PageController extends Controller
{

    public function __construct(
        private VerificationService $verificationService,
        private FundingService $fundingService,
        private NotificationService $notificationService,
        private FirebaseUserRepository $firebaseUsers,
        private FirebaseAdminPostRepository $adminPosts
    ) {
    }
    public function landing()
    {
        return view('pages.landing', ['posts' => $this->adminPostsFor(3, publicOnly: true)]);
    }
    public function dashboardUser()
    {
        $notifications = $this->notificationService->getByUser(Auth::id());
        $notificationCount = count($notifications);
        $fundRequests = $this->fundingService->getRequestsByUser(Auth::id());
        $fundRequestCount = count($fundRequests);

        $posts = $this->adminPostsFor(5);

        return view('users.dashboarduser', compact('fundRequestCount', 'fundRequests', 'notificationCount', 'notifications', 'posts'));
    }

    public function requestStatus()
    {
        $presenter = app(FundingRequestPresenter::class);
        $requests = array_map(function (array $request) use ($presenter): array {
            $status = match ($this->fundingService->statusOf($request)) {
                'approved' => 'Approved',
                'rejected' => 'Denied',
                'completed' => 'Completed',
                default => 'Pending',
            };
            $color = match ($status) {
                'Approved', 'Completed' => 'green',
                'Denied' => 'red',
                default => 'gold',
            };
            $view = $presenter->present($request);

            return [
                'id' => (string) ($request['id'] ?? ''),
                'title' => $request['title'] ?? $request['org_name'] ?? 'Funding request',
                'category' => $request['category_name'] ?? $request['category'] ?? 'General',
                'amount' => $this->fundingService->grantedAmountOf($request),
                'status' => $status,
                'stage' => $view['stage']['label'],
                'color' => $color,
                'date' => $request['updated_at'] ?? $request['created_at'] ?? now()->toIso8601String(),
                'timeline' => $view['timeline'],
                'reason' => $view['rejection_reason'],
                'appeals' => (int) ($request['appeals'] ?? 0),
            ];
        }, $this->fundingService->getRequestsByUser(Auth::id()));

        usort($requests, static fn (array $first, array $second): int => strcmp($second['date'], $first['date']));

        $requestCounts = [
            'Total requests' => count($requests),
            'Pending review' => count(array_filter($requests, static fn (array $request): bool => $request['status'] === 'Pending')),
            'Approved / completed' => count(array_filter($requests, static fn (array $request): bool => in_array($request['status'], ['Approved', 'Completed'], true))),
            'Denied' => count(array_filter($requests, static fn (array $request): bool => $request['status'] === 'Denied')),
        ];

        return view('users.flow', [
            'screen' => 'request-status',
            'requests' => $requests,
            'requestCounts' => $requestCounts,
        ]);
    }

    public function activity()
    {
        $requests = $this->fundingService->getRequestsByUser(Auth::id());
        $getStatus = static function (array $request): string {
            $status = match ((int) ($request['status_id'] ?? 0)) {
                2 => 'Approved',
                3 => 'Denied',
                default => ucfirst(strtolower((string) ($request['status_name'] ?? $request['status'] ?? 'Pending'))),
            };

            return $status === 'Rejected' ? 'Denied' : $status;
        };

        $successfulRequests = array_filter($requests, fn (array $request): bool => in_array($getStatus($request), ['Approved', 'Completed'], true));
        $totalRequests = count($requests);
        // Funds count as received once the foundation records the release, at the amount released.
        $releasedRequests = array_filter($requests, static fn (array $request): bool => ! empty($request['disbursement']['released_at'] ?? null));
        $releasedAmount = fn (array $request): float => (float) ($request['disbursement']['amount'] ?? $this->fundingService->grantedAmountOf($request));
        $receivedAmount = array_sum(array_map($releasedAmount, $releasedRequests));
        $deniedRequests = count(array_filter($requests, fn (array $request): bool => $getStatus($request) === 'Denied'));

        $months = [];
        $monthCursor = now()->startOfMonth()->subMonths(5);
        for ($index = 0; $index < 6; $index++) {
            $key = $monthCursor->format('Y-m');
            $months[$key] = [
                'label' => $monthCursor->format('M'),
                'amount' => 0,
            ];
            $monthCursor->addMonth();
        }

        foreach ($releasedRequests as $request) {
            try {
                $key = \Carbon\Carbon::parse($request['disbursement']['released_at'])->format('Y-m');
            } catch (\Exception) {
                continue;
            }

            if (isset($months[$key])) {
                $months[$key]['amount'] += $releasedAmount($request);
            }
        }

        $maxChartAmount = max(1, ...array_column($months, 'amount'));

        return view('users.flow', [
            'screen' => 'activity',
            'activityData' => [
                'totalRequests' => $totalRequests,
                'receivedAmount' => $receivedAmount,
                'deniedRequests' => $deniedRequests,
                'successRate' => $totalRequests > 0 ? round(count($successfulRequests) / $totalRequests * 100) : 0,
                'months' => $months,
                'maxChartAmount' => $maxChartAmount,
            ],
        ]);
    }

    /**
     * Open a notification: it moves to the history and the page it is about opens (the request,
     * the price list...). Without a link, back to the notifications page.
     */
    public function markNotificationRead(string $id)
    {
        $notification = $this->notificationService->markAsRead($id, Auth::id());
        if ($notification === null) {
            return redirect()->route('notifications')->with('alert_error', 'Notification could not be found.');
        }

        $link = NotificationService::safeLink($notification['link'] ?? null)
            ?? (! empty($notification['request_id']) && Auth::user()->isUser() ? route('fund-request.show', $notification['request_id'], false) : null);

        return $link ? redirect()->to($link) : redirect()->route('notifications')->with('status', 'Notification marked as read.');
    }

    public function markAllNotificationsRead(Request $request)
    {
        $count = $this->notificationService->markAllAsRead(Auth::id());
        $message = $count ? "{$count} notification(s) marked as read." : 'You are all caught up.';

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'count' => $count, 'message' => $message])
            : back()->with('status', $message);
    }
    public function user()
    {
        if (! Auth::user()->isUser()) {
            return redirect()->route('landing')->with(
                'alert_error',
                'That profile page is available only to regular users.'
            );
        }

        return view('users.user');
    }

    /**
     * Latest admin updates; an unreachable Firebase must not break the page.
     */
    private function adminPostsFor(int $limit, bool $publicOnly = false): array
    {
        try {
            return $this->adminPosts->latest($limit, $publicOnly);
        } catch (\Throwable) {
            return [];
        }
    }
    public function iotMonitor()
    {
        return view('pages.iot-monitor');
    }
    public function about()
    {
        return view('pages.about');
    }
    public function login()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->isAdmin()) {
                return redirect()->route('admin');
            } elseif ($user->isSuperAdmin()) {
                return redirect()->route('superadmin');
            } elseif ($user->isUser()) {
                return redirect()->route('dashboarduser');
            }
        }
        return view('pages.login');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('landing');
    }
    public function fundRequest(FundingRules $rules)
    {
        $categories = collect($rules->categories())->map(fn (array $category, string $key): array => [
            'key' => $key,
            'label' => $category['label'],
            'requirements' => $rules->requirementsFor($key),
            'per_person_cap' => $category['per_person_cap'],
        ])->values()->all();

        return view('FundPage.fund-request', [
            'categories' => $categories,
            'denialReason' => $this->fundingService->requestDenialReason(Auth::id()),
        ]);
    }

    public function showFundRequest(string $id)
    {
        $fundRequest = $this->fundingService->getRequestById($id);

        abort_if(
            $fundRequest === null || (string) ($fundRequest['user_id'] ?? '') !== (string) Auth::id(),
            404
        );

        $view = app(FundingRequestPresenter::class)->present($fundRequest);
        $messages = app(\App\Services\RequestMessageService::class);
        $thread = $messages->thread($id, 'requester');
        $messages->markRead($id, 'requester');

        return view('FundPage.fund-request-detail', compact('fundRequest', 'view', 'thread'));
    }

    public function settings()
    {
        return view('pages.settings');
    }

    /**
     * Preferences save as soon as a switch changes (one field per request); the settings form
     * without JavaScript sends both. Fetch requests get JSON back.
     */
    public function updateSettings(Request $request, \App\Services\MailSettingsService $mail, \App\Services\AuditLogger $audit)
    {
        $request->validate([
            'email_notifications' => 'sometimes|boolean',
            'dark_mode' => 'sometimes|boolean',
        ]);

        $user = Auth::user();
        $changes = [];
        foreach (['email_notifications', 'dark_mode'] as $field) {
            if ($request->has($field)) {
                $changes[$field] = $request->boolean($field);
            }
        }

        $enablingCodes = ($changes['email_notifications'] ?? false) && ! filter_var($user->email_notifications ?? true, FILTER_VALIDATE_BOOLEAN);
        if ($enablingCodes && ! $mail->canSend()) {
            // The code could not be delivered, so turning this on would lock the account out.
            $message = 'Email sending is not set up yet, so sign-in codes cannot be delivered. Ask the super admin to configure the verification email first.';

            return $request->expectsJson()
                ? response()->json(['saved' => false, 'message' => $message], 422)
                : back()->with('alert_error', $message);
        }

        if ($changes !== []) {
            $this->firebaseUsers->update($user->getAuthIdentifier(), $changes);
        }
        if (array_key_exists('email_notifications', $changes)
            && $changes['email_notifications'] !== filter_var($user->email_notifications ?? true, FILTER_VALIDATE_BOOLEAN)) {
            $audit->record('account', 'Email verification turned ' . ($changes['email_notifications'] ? 'on' : 'off'), (string) $user->getAuthIdentifier());
        }

        $message = match (true) {
            array_key_exists('email_notifications', $changes) && count($changes) === 1 => $changes['email_notifications']
                ? 'Email verification is on. Sign-in will ask for a code sent to ' . $user->email . '.'
                : 'Email verification is off.',
            array_key_exists('dark_mode', $changes) && count($changes) === 1 => 'Dark mode ' . ($changes['dark_mode'] ? 'on.' : 'off.'),
            default => 'Preferences saved.',
        };

        return $request->expectsJson()
            ? response()->json(['saved' => true, 'message' => $message, 'preferences' => $changes])
            : back()->with('status', $message);
    }
}
