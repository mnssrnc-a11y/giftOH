<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Mail\PasswordResetCode;
use Illuminate\Support\Facades\Mail;
use App\Mail\LoginAuthCode;
use App\Mail\VerifyEmail;
use App\Rules\RealEmail;

class AccountController extends Controller
{
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
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'required|date',
            'street_address' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'province' => 'required|string|max:255',
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
        DB::table('register_verification_codes')->updateOrInsert(
            ['email' => $validated['email']],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        // Send the verification code via email
        try {
            Mail::to($validated['email'])
                ->send(new VerifyEmail($code, $validated['fname']));
        } catch (\Exception $error) {
            // Clean up the verification code since email failed
            DB::table('register_verification_codes')->where('email', $validated['email'])->delete();
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


    /**
     * Show the verify code form.
     */
    public function showVerifyCodeForm(Request $request)
    {
        $email = $request->query('email', old('email'));
        if (!$email) {
            return redirect()->route('forgot-password');
        }
        return view('pages.verify-code', ['email' => $email]);
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
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'email' => 'These credentials do not match our records.',
            ])->onlyInput('email');
        }
        // Generate a random 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // Store the hashed code in the database
        DB::table('login_auth_codes')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );
        // Send the code via Gmail
        Mail::to($request->email)->send(new \App\Mail\LoginAuthCode($code, $user->fname));
        // Store verification details in session
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
        // the input field for email should be remove and the email on the login page will be used to send the reset code.
        // The user will be redirected to the verify code page after submitting the email.
        return view('pages.forgot-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|confirmed|min:8',
        ]);
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();
        if (!$record || !Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'Invalid or expired reset session. Please start over.']);
        }
        // Check expiry (15 minutes from when the token was refreshed)
        if (now()->diffInMinutes($record->created_at) > 15) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors(['email' => 'Reset session expired. Please start over.']);
        }
        // Update password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();
        // Clean up the token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        return redirect()->route('login')->with('status', 'Your password has been reset successfully. Please sign in.');
    }

        public function sendResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->with('alert_error', 'Please enter the correct email.')->onlyInput('email');
        }
        // Generate a random 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // Delete any existing reset tokens for this email
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        // Store the hashed code in the database
        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => Hash::make($code),
            'created_at' => now(),
        ]);
        // Send the code via Gmail
        Mail::to($request->email)->send(new PasswordResetCode($code, $user->fname));
        return redirect()->route('password.verify-code.form', ['email' => $request->email])
            ->with('status', 'We sent a 6-digit code to your email.');
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
        $user->password = Hash::make($request->password);
        $user->save();
        return redirect()->route('settings')->with('success', 'Password changed successfully.');
    }

}
