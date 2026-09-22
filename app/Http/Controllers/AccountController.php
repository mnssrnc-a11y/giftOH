<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\FirebaseUser;
use App\Services\VerificationService;
use App\Mail\PasswordResetCode;
use Illuminate\Support\Facades\Mail;
use App\Mail\LoginAuthCode;
use App\Mail\VerifyEmail;
use App\Rules\RealEmail;
use App\Repositories\FirebaseUserRepository;
use App\Services\NotificationService;
use app\Services\FirebaseService;

class AccountController extends Controller
{
    protected $firebaseService;

    public function __construct(
        private FirebaseUserRepository $firebaseUsers,
        private VerificationService $verificationService,
        private NotificationService $notificationService
    ) {
    }

    public function register()
    {
        return view('pages.register');
    }

    public function storeRegister(Request $request)
    {
        $validated = $request->validate([
            'fname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'mname' => 'nullable|string|max:255',
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users', new RealEmail],
            'password' => 'required|confirmed|min:8',
            'contact_number' => 'required|string|max:15',
            'gender' => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date',
            'street_address' => 'nullable|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Concatenate address parts into a single address string
        $address = $validated['street_address'] . ', '
                . $validated['barangay'] . ', '
                . $validated['city'] . ', '
                . $validated['province'];

        // Generate a random 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed code in the database
        $this->verificationService->store($validated['email'], $code, 'register_verification_codes');

        // Send the verification code via email
        try {
            Mail::to($validated['email'])
                ->send(new VerifyEmail($code, $validated['fname']));
        } catch (\Exception $error) {
            // Clean up the verification code since email failed
            $this->verificationService->forget($validated['email'], 'register_verification_codes');
            return back()
                ->withInput()
                ->with('alert_error',
                    'The email address you provided is not eligible. Please use a valid email to register.');
        }

        // Store pending registration data in session
        session([
            'pending_registration' => [
                'fname' => $validated['fname'],
                'lname' => $validated['lname'],
                'mname' => $validated['mname'] ?? null,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['contact_number'],
                'address' => $address,
                'gender' => $validated['gender'],
                'date_of_birth' => $validated['date_of_birth'],
                'profile_picture' => $validated['profile_picture'] ?? null,
            ],
        ]);

        return redirect()->route('register.verify-code.form', ['email' => $validated['email']])
            ->with('status', 'We sent a 6-digit verification code to your email.');
    }

    /**
     * Show the register verification form.
     */
    public function showRegisterVerifyForm(Request $request)
    {
        $email = $request->query('email', session('pending_registration.email'));
        if (!$email || !session()->has('pending_registration')) {
            return redirect()->route('register');
        }
        return view('pages.register-verify', ['email' => $email]);
    }


    // show verify code for password
    public function showVerifyCodeForm(Request $request)
    {
        $email = $request->query('email', old('email'));
        if (!$email) {
            return redirect()->route('forgot-password');
        }
        return view('pages.verify-code', ['email' => $email]);
    }

    // show verify code form
    public function showVerifyCodeFormEmail(Request $request)
    {
        $email = $request->query('email', old('email'));
        if (!$email){
            return redirect()->route('edit-email');
        }
        return view('user.', ['email' => $email]);
    }


    /**
     * Show the new password form.
     */
    public function showResetForm(Request $request)
    {
        return view('pages.reset-password', [
            'email' => $request->query('email'),
            'token' => $request->query('token'),
        ]);
    }

    public function showLoginVerifyForm(Request $request)
    {
        $email = $request->query('email', session('login_2fa_email'));
        if (!$email) {
            return redirect()->route('login');
        }
        return view('pages.login-verify', ['email' => $email]);
    }

    public function storeLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'nullable|in:user,admin',
        ]);
        $user = $this->firebaseUsers->findByEmail($request->email);
        $passwordHash = is_array($user) ? ($user['password'] ?? '') : ($user->password ?? '');

        if (!$user || !Hash::check($request->password, $passwordHash)) {
            return back()->withErrors([
                'email' => 'These credentials do not match our records.',
            ])->onlyInput('email');
        }

        $isActive = is_array($user) ? ($user['is_active'] ?? true) : $user->is_active;
        if (! $isActive) {
            return back()->withErrors([
                'email' => 'This account is inactive.',
            ])->onlyInput('email');
        }

        if (! filter_var($user['email_notifications'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            Auth::login(is_array($user) ? new FirebaseUser($user) : $user, $request->boolean('remember'));
            $request->session()->regenerate();

            $authenticatedUser = is_array($user) ? new FirebaseUser($user) : $user;
            session()->forget('alert_error');

            return redirect()->route($authenticatedUser->isAdmin() ? 'admin' : 'dashboarduser');
        }

        // Generate a random 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->verificationService->store($request->email, $code, 'login_auth_codes');
        $firstName = is_array($user) ? ($user['fname'] ?? '') : $user->fname;
        Mail::to($request->email)->send(new \App\Mail\LoginAuthCode($code, $firstName));
        session([
            'login_2fa_email' => $request->email,
            'login_2fa_remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('login.verify-code.form', ['email' => $request->email])
            ->with('status', 'We sent a 6-digit verification code to your email.');
    }

    /**
     * Handle password change request.
     */

        public function showChangePasswordForm()
    {
        return view('pages.change-password');
    }

        public function forgotPassword()
    {
        return view('pages.forgot-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|confirmed|min:8',
        ]);
        $record = $this->verificationService->find($request->email, 'password_reset_tokens');
        if (!$record || !Hash::check($request->token, $record['token'])) {
            return back()->withErrors(['email' => 'Invalid or expired reset session. Please start over.']);
        }
        if (now()->diffInMinutes($record['created_at']) > 5) {
            $this->verificationService->forget($request->email, 'password_reset_tokens');
            return back()->withErrors(['email' => 'Reset session expired. Please start over.']);
        }
        // Update password
        $userData = $this->firebaseUsers->findByEmail($request->email);
        $user = is_array($userData) ? new FirebaseUser($userData) : $userData;
        $this->firebaseUsers->update($user->getAuthIdentifier(), [
            'password' => Hash::make($request->password),
        ]);
        // Clean up the token
        $this->verificationService->forget($request->email, 'password_reset_tokens');
        return redirect()->route('login')->with('status', 'Your password has been reset successfully. Please sign in.');
    }

        public function sendResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        $userData = $this->firebaseUsers->findByEmail($request->email);
        $user = is_array($userData) ? new FirebaseUser($userData) : $userData;
        if (!$user) {
            return back()->with('alert_error', 'Please enter the correct email.')->onlyInput('email');
        }
        if (! filter_var($userData['email_notifications'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return back()->with('alert_error', 'Email notifications are disabled for this account.')->onlyInput('email');
        }
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->verificationService->store($request->email, $code, 'password_reset_tokens');
        Mail::to($request->email)->send(new PasswordResetCode($code, $user->fname));
        return redirect()->route('password.verify-code.form', ['email' => $request->email])
            ->with('status', 'We sent a 6-digit code to your email.');
    }

    public function notifications()
    {
        $userId = Auth::id();
        $notifications = $this->notificationService->getByUser($userId);
        return view('users.notifications', ['notifications' => $notifications]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|confirmed|min:8',
        ]);
        $user = Auth::user();
        // Check if current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password does not match.']);
        }
        // Update password
        $this->firebaseUsers->update($user->getAuthIdentifier(), [
            'password' => Hash::make($request->password),
        ]);
        return redirect()->route('settings')->with('success', 'Password changed successfully.');
    }

    public function updateUser(Request $request)
    {
        $validated = $request->validate([
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['required', 'string', 'max:255'],
        ]);
        $currentUser = Auth::user();

        $this->firebaseUsers->update($currentUser->getAuthIdentifier(), [
            'fname' => $validated['fname'],
            'lname' => $validated['lname'],
            'contact_number' => $request->input('contact_number', $currentUser->contact_number),
        ]);

        return redirect()->route('settings')->with('status', 'Your profile has been updated successfully.');
    }

    public function updateProfilePicture(Request $request)
    {
        $request->validate([
            'profile_picture' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ], [
            'profile_picture.mimes' => 'The photo must be a JPG or PNG file.',
            'profile_picture.image' => 'The selected file is not a valid image.',
            'profile_picture.max' => 'The photo must not be larger than 5 MB.',
        ]);

        $user = Auth::user();
        $oldPath = $user->profile_picture;

        // Laravel generates a random file name, so the original name is never trusted or reused.
        $path = $request->file('profile_picture')->store('profile_pictures', 'public');

        // Save the path on the signed-in user's own Firebase record (id comes from the session, not the request).
        $updated = $this->firebaseUsers->update($user->getAuthIdentifier(), [
            'profile_picture' => $path,
        ]);

        if ($updated === null) {
            Storage::disk('public')->delete($path);

            return back()->withErrors(['profile_picture' => 'We could not save your photo. Please try again.']);
        }

        // Remove the previous photo, but only if it is one of our own uploads.
        if (is_string($oldPath)
            && $oldPath !== $path
            && str_starts_with($oldPath, 'profile_pictures/')
            && ! str_contains($oldPath, '..')) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('settings')
            ->with('profile_picture_status', 'Your profile picture has been updated.');
    }
}