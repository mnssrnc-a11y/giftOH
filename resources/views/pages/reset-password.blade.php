@extends('layouts.public')

@section('title', 'Reset Password - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-8">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
                <div class="text-center mb-8">
                    <div class="bg-green-500 w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Set New Password</h1>
                    <p class="text-gray-600">Create a strong password for your account.</p>
                </div>

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <input type="hidden" name="token" value="{{ $token }}">

                    @if ($errors->any())
                        <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-lg">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm text-red-700">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="mb-5">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                        <input id="password" name="password" type="password" required autofocus minlength="8" placeholder="Enter new password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"/>
                    </div>

                    <div class="mb-6">
                        <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" placeholder="Confirm new password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"/>
                    </div>

                    <button type="submit" class="w-full bg-[#1E3A8A] text-white py-3 rounded-lg font-bold hover:bg-[#2d4a9e] transition-colors flex items-center justify-center gap-2"> Reset Password </button>
                </form>
            </div>

            <p class="text-xs text-gray-500 text-center mt-6">
                Your password must be at least 8 characters long.
            </p>
        </div>
    </div>
@endsection
