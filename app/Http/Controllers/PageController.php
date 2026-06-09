<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
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
        return view('pages.dashboardUser');
    }

    public function user()
    {
        return view('pages.user');
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
        return view('pages.login');
    }

    public function storeLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'These credentials do not match our records.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route('dashboarduser');
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

    public function forgotPassword()
    {
        return view('pages.forgot-password');
    }

    public function fundRequest()
    {
        return view('pages.fund-request');
    }

    public function settings()
    {
        return view('pages.settings');
    }

    public function storeRegister(Request $request)
    {
        $validated = $request->validate([
            'fname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'mname' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|confirmed|min:8',
        ]);

        $user = User::create([
            'fname' => $validated['fname'],
            'lname' => $validated['lname'],
            'mname' => $validated['mname'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return redirect()->route('login');
    }
}
