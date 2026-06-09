@extends('layouts.public')

@section('title', 'Login - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-8">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
                <div class="text-center mb-8">
                    <div class="bg-[#3B82F6] w-16 h-16 rounded-full mx-auto mb-4"></div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h1>
                    <p class="text-gray-600">Sign in to your Gift of Hope account</p>
                </div>

                <form method="POST" action="{{ route('login.store') }}">
                    @csrf

                    @if ($errors->any())
                        <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-lg">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm text-red-700">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            placeholder="username@gmail.com"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                        />
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input
                            name="password"
                            type="password"
                            placeholder="Enter your password"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                        />
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center">
                            <input name="remember" type="checkbox" class="w-4 h-4 text-[#3B82F6] border-gray-300 rounded focus:ring-[#3B82F6]" />
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>
                        <a href="{{ route('forgot-password') }}" class="text-sm text-[#3B82F6] hover:underline">Forgot password?</a>
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-[#1E3A8A] text-white py-3 rounded-lg font-bold hover:bg-[#2d4a9e] transition-colors flex items-center justify-center gap-2"
                    >
                        Sign In
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="text-[#3B82F6] font-semibold hover:underline">Sign up</a>
                    </p>
                </div>
            </div>

            <p class="text-xs text-gray-500 text-center mt-6">
                By signing in, you agree to our Terms of Service and Privacy Policy
            </p>
        </div>
    </div>
@endsection

