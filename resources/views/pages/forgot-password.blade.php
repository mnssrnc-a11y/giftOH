@extends('layouts.public')

@section('title', 'Forgot Password - Gift of Hope')

@section('content')
    <div class="brand-auth">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
                <div class="text-center mb-8">
                    <div class="bg-[#1976D2] w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Forgot Password?</h1>
                    <p class="text-gray-600">No worries! Enter your email and we'll send you a reset link.</p>
                </div>

                <form method="POST" action="{{ route('password.send-code') }}">
                    @csrf

                    @if (session('status'))
                        <div class="mb-5 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <p class="text-sm text-green-700">{{ session('status') }}</p>
                        </div>
                    @endif

                    @if (session('alert_error'))
                        <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-lg" role="alert">
                            <p class="text-sm text-red-700">{{ session('alert_error') }}</p>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-lg">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm text-red-700">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="mb-6">
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autofocus
                            value="{{ old('email') }}"
                            placeholder="admin@giftofhope.org"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1976D2]"
                        />
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-[#0D47A1] text-white py-3 rounded-lg font-bold hover:bg-[#1565C0] transition-colors flex items-center justify-center gap-2"
                    >
                        Send Reset Link
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Remember your password?
                        <a href="{{ route('login') }}" class="text-[#1976D2] font-semibold hover:underline">Back to Sign In</a>
                    </p>
                </div>
            </div>

            <p class="text-xs text-blue-50 text-center mt-6">
                We'll send a password reset link to your registered email address.
            </p>
        </div>
    </div>
@endsection
