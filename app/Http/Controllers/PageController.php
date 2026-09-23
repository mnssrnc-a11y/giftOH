<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetCode;
use App\Services\FundingService;
use App\Services\NotificationService;
use App\Services\VerificationService;
use App\Repositories\FirebaseUserRepository;
use Illuminate\Support\Str;
use app\Services\FirebaseService;
class PageController extends Controller
{
    protected $firebaseService;

    public function __construct(
        private VerificationService $verificationService,
        private FundingService $fundingService,
        private NotificationService $notificationService,
        private FirebaseUserRepository $firebaseUsers
    ) {
    }
    public function landing()
    {
        return view('pages.landing');
    }
    public function dashboard()
    {
        return view('pages.dashboard');
    }
    public function dashboardUser()
    {
        $notifications = $this->notificationService->getByUser(Auth::id());
        $notificationCount = count($notifications);
        $fundRequests = $this->fundingService->getRequestsByUser(Auth::id());
        $fundRequestCount = count($fundRequests);

        return view('users.dashboarduser', compact('fundRequestCount', 'fundRequests', 'notificationCount', 'notifications'));
    }

    public function requestStatus()
    {
        $requests = array_map(function (array $request): array {
            $status = match ((int) ($request['status_id'] ?? 0)) {
                2 => 'Approved',
                3 => 'Denied',
                default => ucfirst(strtolower((string) ($request['status_name'] ?? $request['status'] ?? 'Pending'))),
            };
            $status = $status === 'Rejected' ? 'Denied' : $status;
            $color = match ($status) {
                'Approved', 'Completed' => 'green',
                'Denied' => 'red',
                default => 'gold',
            };

            return [
                'id' => (string) ($request['id'] ?? ''),
                'title' => $request['title'] ?? $request['org_name'] ?? 'Funding request',
                'category' => $request['category_name'] ?? $request['category'] ?? 'General',
                'amount' => (float) ($request['amount_requested'] ?? $request['amount'] ?? 0),
                'status' => $status,
                'color' => $color,
                'date' => $request['updated_at'] ?? $request['created_at'] ?? now()->toIso8601String(),
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
        $receivedAmount = array_sum(array_map(static fn (array $request): float => (float) ($request['amount_requested'] ?? $request['amount'] ?? 0), $successfulRequests));
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

        foreach ($successfulRequests as $request) {
            $date = $request['updated_at'] ?? $request['created_at'] ?? null;
            if ($date === null) {
                continue;
            }

            try {
                $key = \Carbon\Carbon::parse($date)->format('Y-m');
            } catch (\Exception) {
                continue;
            }

            if (isset($months[$key])) {
                $months[$key]['amount'] += (float) ($request['amount_requested'] ?? $request['amount'] ?? 0);
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

    public function markNotificationRead(string $id)
    {
        $marked = $this->notificationService->markAsRead($id, Auth::id());

        return redirect()->route('dashboarduser')->with(
            $marked ? 'status' : 'alert_error',
            $marked ? 'Notification moved to history.' : 'Notification could not be found.'
        );
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
    public function iotMonitor()
    {
        return view('pages.iot-monitor');
    }
    public function reports()
    {
        return view('pages.reports');
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
    public function register()
    {
        return view('pages.register');
    }

    public function fundRequest()
    {
        return view('FundPage.fund-request');
    }

    public function showFundRequest(string $id)
    {
        $fundRequest = $this->fundingService->getRequestById($id);

        abort_if(
            $fundRequest === null || (string) ($fundRequest['user_id'] ?? '') !== (string) Auth::id(),
            404
        );

        return view('FundPage.fund-request-detail', compact('fundRequest'));
    }

    public function showFundRequestVerifyForm()
    {
        if (!session()->has('pending_fund_request')) {
            return redirect()->route('fund-request')->with('alert_error', 'No pending transaction found.');
        }
        return view('FundPage.fund-request-verify', ['email' => Auth::user()->email]);
    }

    public function initiateApprovalAction(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approved,rejected',
            'notes' => 'nullable|string',
        ]);

        $funding = $this->fundingService->getRequestById($id);
        abort_if($funding === null, 404);
        $user = Auth::user();

        if (! filter_var($user->email_notifications ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return back()->with('alert_error', 'Email notifications are disabled for this account.');
        }

        // Generate a random 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->verificationService->store($user->email, $code, 'approval_verification_codes');

        // Send the code via email
        Mail::to($user->email)->send(new \App\Mail\ApprovalVerificationCode($code, $user->fname, $request->action));

        // Save pending approval details to session
        session([
            'pending_approval' => [
                'request_id' => (string) $id,
                'action' => $request->action,
                'notes' => $request->notes,
            ]
        ]);

        return redirect()->route('admin.fund-request.verify.form')
            ->with('status', 'A 6-digit approval verification code has been sent to your email.');
    }

    public function showApprovalVerifyForm()
    {
        if (!session()->has('pending_approval')) {
            return redirect()->route('admin')->with('alert_error', 'No pending approval found.');
        }
        return view('pages.admin-approval-verify', ['email' => Auth::user()->email]);
    }

    public function settings()
    {
        return view('pages.settings');
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'email_notifications' => 'nullable|boolean',
            'dark_mode' => 'nullable|boolean',
        ]);

        $this->firebaseUsers->update(Auth::user()->getAuthIdentifier(), [
            'email_notifications' => $request->boolean('email_notifications'),
            'dark_mode' => $request->boolean('dark_mode'),
        ]);

        return redirect()->route('settings')->with('status', 'Preferences updated successfully.');
    }
}
