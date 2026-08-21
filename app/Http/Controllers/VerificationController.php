<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\VerificationService;
use App\Models\User;
use App\Models\Funding;
use App\Models\FundingApproval;
use App\Models\FirebaseUser;
use App\Repositories\FirebaseUserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use app\Services\FirebaseService;

class VerificationController extends Controller
{
    protected $verificationService;
    protected $firebaseService;

    public function __construct(
        VerificationService $verificationService,
        private FirebaseUserRepository $firebaseUsers
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
        $userData = $this->firebaseUserProviderEnabled()
            ? $this->firebaseUsers->findByEmail($request->email)
            : User::where('email', $request->email)->first();
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

        if ($user->role == 'admin') {
            return redirect()->route('admin');
        } else {
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

        $user = $this->firebaseUserProviderEnabled()
            ? $this->firebaseUsers->findByEmail($request->email)
            : User::where('email', $request->email)->first();
        if (!$user) {
            return back()->with('alert_error', 'Invalid email address.');
        }

        // Generate code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed code in database
        $this->verificationService->store($request->email, $code, 'login_auth_codes');

        // Send email
        $firstName = is_array($user) ? ($user['fname'] ?? '') : $user->fname;
        Mail::to($request->email)->send(new \App\Mail\LoginAuthCode($code, $firstName));

        return back()->with('status', 'A new 6-digit verification code has been sent to your email.');
    }

    private function firebaseUserProviderEnabled(): bool
    {
        return config('auth.providers.users.driver') === 'firebase';
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

        $userData = [
            'fname' => $data['fname'],
            'lname' => $data['lname'],
            'mname' => $data['mname'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'gender' => $data['gender'],
            'date_of_birth' => $data['date_of_birth'],
            'profile_picture' => $data['profile_picture'],
        ];

        $user = $this->firebaseUserProviderEnabled()
            ? new FirebaseUser($this->firebaseUsers->create($userData))
            : User::create($userData);
        // Clean up
        $this->verificationService->forget($request->email, 'register_verification_codes');
        session()->forget('pending_registration');

        // Log the user in
        Auth::login($user);
        $request->session()->regenerate();

        if ($user->role == 'admin') {
            return redirect()->route('admin');
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

        $this->verificationService->store($request->email, $code, 'register_verification_codes');

        Mail::to($request->email)->send(new \App\Mail\VerifyEmail($code, $data['fname']));

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    /**
     * Verify the 6-digit code for fund request transaction.
     */
    public function verifyFundRequest(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if (!session()->has('pending_fund_request')) {
            return redirect()->route('fund-request')->with('alert_error', 'Session expired. Please request again.');
        }

        $user = Auth::user();
        $result = $this->verificationService->verify($user->email, $request->code, 'transaction_verification_codes');

        if (!$result['success']) {
            return back()->withErrors(['code' => $result['error']])->withInput();
        }

        // Code is valid - retrieve data and insert into db
        $data = session('pending_fund_request');
        
        $funding = Funding::create([
            'user_id' => $user->id,
            'category_id' => $data['category_id'],
            'status_id' => 1, // Pending
            'title' => $data['title'],
            'description' => $data['description'],
            'amount_requested' => $data['amount_requested'],
            'amount_paid' => 0.00,
        ]);

        // Run AI scoring on the newly created funding request
        try {
            $aiResult = \App\Http\Controllers\aiActionController::scoreFundingRequest($funding);
            if ($aiResult) {
                $funding->update([
                    'ai_score' => $aiResult['total_score'],
                    'ai_score_breakdown' => $aiResult,
                ]);
            }
        } catch (\Exception $e) {
            // AI scoring failure should not block the request submission
            \Illuminate\Support\Facades\Log::warning('AI scoring failed for funding request #' . $funding->id . ': ' . $e->getMessage());
        }

        // Clean up
        $this->verificationService->forget($user->email, 'transaction_verification_codes');
        session()->forget('pending_fund_request');

        return redirect()->route('dashboarduser')->with('status', 'Your funding request has been submitted successfully.');
    }

    /**
     * Resend verification code for fund transaction request.
     */
    public function resendFundRequestCode()
    {
        if (!session()->has('pending_fund_request')) {
            return redirect()->route('fund-request')->with('alert_error', 'Session expired. Please request again.');
        }

        $user = Auth::user();
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->verificationService->store($user->email, $code, 'transaction_verification_codes');

        Mail::to($user->email)->send(new \App\Mail\TransactionVerificationCode($code, $user->fname));

        return back()->with('status', 'A new transaction verification code has been sent to your email.');
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
        $result = $this->verificationService->verify($user->email, $request->code, 'approval_verification_codes');

        if (!$result['success']) {
            return back()->withErrors(['code' => $result['error']])->withInput();
        }

        $data = session('pending_approval');
        $funding = Funding::findOrFail($data['request_id']);

        if ($data['action'] === 'approved') {
            $funding->status_id = 2; // Approved
            $funding->approved_at = now();
            $funding->approved_by = $user->id;
        } else {
            $funding->status_id = 3; // Rejected
            $funding->rejected_at = now();
            $funding->approved_by = $user->id;
        }
        $funding->save();

        // Create approval record
        FundingApproval::create([
            'request_id' => $funding->id,
            'approved_by' => $user->id,
            'approval_status' => $data['action'],
            'approval_notes' => $data['notes'] ?? null,
            'decision_at' => now(),
        ]);

        // Clean up
        $this->verificationService->forget($user->email, 'approval_verification_codes');
        session()->forget('pending_approval');

        return redirect()->route('admin')->with('status', 'Funding request updated successfully.');
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
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $action = session('pending_approval.action');

        $this->verificationService->store($user->email, $code, 'approval_verification_codes');

        Mail::to($user->email)->send(new \App\Mail\ApprovalVerificationCode($code, $user->fname, $action));

        return back()->with('status', 'A new approval verification code has been sent to your email.');
    }
}