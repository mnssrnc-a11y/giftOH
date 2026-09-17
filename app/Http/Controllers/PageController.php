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

        return view('users.dashboardUser', compact('fundRequestCount', 'fundRequests', 'notificationCount', 'notifications'));
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
            if ($user->role === 'admin') {
                return redirect()->route('admin');
            } elseif ($user->role === 'user') {
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

        $funding = \App\Models\Funding::findOrFail($id);
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
                'request_id' => $funding->id,
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
        $currentUser = Auth::user();

        $this->firebaseUsers->update($currentUser->getAuthIdentifier(), [
            'email_notifications' => $request->boolean('email_notifications'),
            'dark_mode' => $request->boolean('dark_mode'),
        ]);

        return redirect()->route('settings')->with('status', 'Preferences updated successfully.');
    }
}