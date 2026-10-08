@extends('layouts.public')

@section('title', 'Login - Gift of Hope')

@section('content')
    <div class="brand-auth">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
                <div class="text-center mb-8">
                    <img src="{{ asset('images/logo-full.png') }}" alt="Gift of Hope, charity home platform" width="768" height="538" class="mx-auto mb-4 h-28 w-auto">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h1>
                    <p class="text-gray-600">Sign in to your Gift of Hope account</p>
                </div>

                <form method="POST" action="{{ route('login.store') }}">
                    @csrf

                    @if (session('status'))
                        <div class="mb-5 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <p class="text-sm text-green-700">{{ session('status') }}</p>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-lg">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm text-red-700">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="mb-5">
                        <label for="login-email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input
                            id="login-email"
                            name="email"
                            required
                            autocomplete="email"
                            type="email"
                            value="{{ old('email', request()->cookie('remembered_email')) }}"
                            placeholder="username@gmail.com"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1976D2]"
                        />
                    </div>

                    <div class="mb-6">
                        <label for="login-password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input
                            id="login-password"
                            name="password"
                            required
                            autocomplete="current-password"
                            type="password"
                            placeholder="Enter your password"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1976D2]"
                        />
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center">
                            <input name="remember" type="checkbox" @checked(request()->cookie('remembered_email')) class="w-4 h-4 text-[#1976D2] border-gray-300 rounded focus:ring-[#1976D2]" />
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>
                        <a href="{{ route('forgot-password') }}" class="text-sm text-[#1976D2] hover:underline">Forgot password?</a>
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-[#0D47A1] text-white py-3 rounded-lg font-bold hover:bg-[#1565C0] transition-colors flex items-center justify-center gap-2"
                    >
                        Sign In
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="text-[#1976D2] font-semibold hover:underline">Sign up</a>
                    </p>
                </div>
                @if(session('alert_error'))
                    <div class="mt-6 text-center p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                        {{ session('alert_error') }}
                    </div>
                @endif
            </div>

            <p class="text-xs text-blue-50 text-center mt-6">
                By signing in, you agree to our Terms of Service and Privacy Policy
            </p>
        </div>
    </div>
@endsection

