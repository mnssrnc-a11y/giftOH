<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\VerificationService;
use App\Services\FundingService;
use App\Models\User;
use App\Models\FirebaseUser;
use App\Repositories\FirebaseUserRepository;
use App\Services\NotificationService;
use App\Services\RegistrationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class VerificationController extends Controller
{
    private const MAIL_FAILED = 'We could not send a new code right now. Please try again later or contact the administrator.';

    protected $verificationService;

    public function __construct(
        VerificationService $verificationService,
        private FundingService $fundingService,
        private FirebaseUserRepository $firebaseUsers,
        private NotificationService $notificationService,
        private RegistrationService $registration
    )
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Verify the 6-digit code for password reset.
     */
    public function verifyPasswordReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $result = $this->verificationService->verify($request->email, $request->code, 'password_reset_tokens');

        if (!$result['success']) {
            return back()->withErrors(['code' => $result['error']])->withInput();
        }

        // Code is valid — generate a one-time token for the reset form
        $resetToken = Str::random(64);

        // Update the token so the code can't be reused
        $this->verificationService->rotate($request->email, 'password_reset_tokens', $resetToken);

        return redirect()->route('password.reset.form', [
            'email' => $request->email,
            'token' => $resetToken,
        ]);
    }

    /**
     * Verify the 6-digit code for login authentication.
     */
    public function verifyLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $result = $this->verificationService->verify($request->email, $request->code, 'login_auth_codes');

        if (!$result['success']) {
            return back()->withErrors(['code' => $result['error']])->withInput();
        }

        // Code is valid — retrieve user and log in
        $userData = $this->firebaseUsers->findByEmail($request->email);
        $user = is_array($userData) ? new FirebaseUser($userData) : $userData;
        if (!$user) {
            return back()->withErrors(['code' => 'User account not found.'])->withInput();
        }

        // Delete the verified code
        $this->verificationService->forget($request->email, 'login_auth_codes');

        // Log the user in
        Auth::login($user, session('login_2fa_remember', false));
        $request->session()->regenerate();
        session()->forget(['login_2fa_email', 'login_2fa_remember']);

        session()->forget('alert_error');

        if ($user->isAdmin()) {
            return redirect()->route('admin');
        }elseif($user->isSuperAdmin()){
            return redirect()->route('superadmin');
        }else {
            return redirect()->route('dashboarduser');
        }
    }

    /**
     * Resend 2FA login verification code.
     */
    public function resendLoginCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = $this->firebaseUsers->findByEmail($request->email);
        if (!$user) {
            return back()->with('alert_error', 'Invalid email address.');
        }

        if (! filter_var($user['email_notifications'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return back()->with('alert_error', 'Email notifications are disabled for this account.');
        }

        // Generate code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Send email, then store the hashed code so a failed send leaves the previous code usable
        $firstName = is_array($user) ? ($user['fname'] ?? '') : $user->fname;
        try {
            Mail::to($request->email)->send(new \App\Mail\LoginAuthCode($code, $firstName));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withErrors(['code' => self::MAIL_FAILED]);
        }

        $this->verificationService->store($request->email, $code, 'login_auth_codes');

        return back()->with('status', 'A new 6-digit verification code has been sent to your email.');
    }

    /**
     * Verify the 6-digit code for registration email verification.
     */
    public function verifyRegister(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        if (!session()->has('pending_registration')) {
            return redirect()->route('register')->with('alert_error', 'Session expired. Please register again.');
        }

        $result = $this->verificationService->verify($request->email, $request->code, 'register_verification_codes');

        if (!$result['success']) {
            return back()->withErrors(['code' => $result['error']])->withInput();
        }

        // Code is valid — retrieve pending registration data and create the user
        $data = session('pending_registration');

        if ($this->firebaseUsers->findByEmail($data['email']) !== null) {
            $this->verificationService->forget($request->email, 'register_verification_codes');
            session()->forget('pending_registration');

            return redirect()->route('register')->withErrors([
                'email' => 'An account with this email address already exists.',
            ]);
        }

        $user = $this->registration->createAccount($data, emailConfirmed: true);
        // Clean up
        $this->verificationService->forget($request->email, 'register_verification_codes');
        session()->forget('pending_registration');

        // Log the user in
        Auth::login($user);
        $request->session()->regenerate();

        if ($user->isAdmin()) {
            return redirect()->route('admin');
        } elseif($user->isSuperAdmin()){
            return redirect()->route('superadmin');
        } else {
            return redirect()->route('dashboarduser');
        }
    }

    /**
     * Resend verification code for registration.
     */
    public function resendRegisterCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        if (!session()->has('pending_registration')) {
            return redirect()->route('register')->with('alert_error', 'Session expired. Please register again.');
        }

        $data = session('pending_registration');
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            Mail::to($request->email)->send(new \App\Mail\VerifyEmail($code, $data['fname']));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withErrors(['code' => self::MAIL_FAILED]);
        }

        $this->verificationService->store($request->email, $code, 'register_verification_codes');

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    /**
     * Verify the 6-digit code for fund approval action.
     */
    public function verifyApprovalAction(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if (!session()->has('pending_approval')) {
            return redirect()->route('admin')->with('alert_error', 'Session expired. Please try again.');
        }

        $user = Auth::user();
        if (! filter_var($user->email_notifications ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return back()->with('alert_error', 'Email notifications are disabled for this account.');
        }
        $result = $this->verificationService->verify($user->email, $request->code, 'approval_verification_codes');

        if (!$result['success']) {
            return back()->withErrors(['code' => $result['error']])->withInput();
        }

        // The admin's decision is a recommendation; the super admin finalizes it.
        $data = session('pending_approval');
        $funding = $this->fundingService->submitAdminReview(
            $data['request_id'],
            $data['action'],
            $user->getAuthIdentifier(),
            $data['amount'] ?? null,
            $data['notes'] ?? null,
            $data['ai_amount'] ?? null
        );

        // Clean up
        $this->verificationService->forget($user->email, 'approval_verification_codes');
        session()->forget('pending_approval');

        if ($funding === null) {
            return AdminController::toSection('funding')->with('alert_error', 'This request is no longer awaiting admin review.');
        }

        $verb = $data['action'] === 'approved' ? 'Approval' : 'Rejection';
        app(\App\Services\AuditLogger::class)->record('funding', "Admin recommended {$data['action']}"
            . (! empty($data['amount']) ? ' of ₱' . number_format($data['amount'], 2) : ''), $data['request_id']);

        return AdminController::toSection('funding')->with('status', "{$verb} recorded and sent to the super admin for finalization.");
    }

    /**
     * Resend verification code for fund approval.
     */
    public function resendApprovalCode()
    {
        if (!session()->has('pending_approval')) {
            return redirect()->route('admin')->with('alert_error', 'Session expired. Please try again.');
        }

        $user = Auth::user();
        if (! filter_var($user->email_notifications ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return back()->with('alert_error', 'Email notifications are disabled for this account.');
        }
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $action = session('pending_approval.action');

        try {
            Mail::to($user->email)->send(new \App\Mail\ApprovalVerificationCode($code, $user->fname, $action));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withErrors(['code' => self::MAIL_FAILED]);
        }

        $this->verificationService->store($user->email, $code, 'approval_verification_codes');

        return back()->with('status', 'A new approval verification code has been sent to your email.');
    }
}