@extends('layouts.admin')

@section('title', 'Verify Approval - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-8">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
                <div class="text-center mb-8">
                    <div class="bg-[#1E3A8A] w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Confirm Decision</h1>
                    <p class="text-gray-600">Enter the verification code sent to <strong class="text-gray-900">{{ Auth::user()->email }}</strong> to verify your decision.</p>
                </div>

                <form method="POST" action="{{ route('admin.fund-approval-verify') }}">
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

                    <div class="mb-6">
                        <label for="code" class="block text-sm font-semibold text-gray-700 mb-2">Enter 6-Digit Code</label>
                        <input id="code" name="code" type="text" required autofocus maxlength="6" placeholder="000000" class="w-full px-4 py-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1E3A8A] text-center text-2xl font-mono tracking-[0.5em] font-bold"/>
                    </div>

                    <button type="submit" class="w-full bg-[#1E3A8A] text-white py-3 rounded-lg font-bold hover:bg-[#2d4a9e] transition-colors flex items-center justify-center gap-2">Verify & Execute Decision</button>
                </form>

                <div class="mt-6 text-center space-y-2">
                    <p class="text-sm text-gray-600">
                        Didn't receive the code?
                        <form method="POST" action="{{ route('admin.fund-request.resend-code') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-[#1E3A8A] font-semibold hover:underline bg-transparent border-0 p-0 cursor-pointer">Resend Code</button>
                        </form>
                    </p>
                    <p class="text-sm text-gray-500">
                        <a href="{{ route('admin') }}" class="text-gray-500 hover:underline">← Cancel & Back to Dashboard</a>
                    </p>
                </div>
            </div>
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500">
                    The code expires in <span id="countdown-timer" class="font-bold text-[#1E3A8A]">05:00</span>.
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let timeLeft = 300; // 5 minutes in seconds
            const timerElement = document.getElementById('countdown-timer');

            const countdown = setInterval(function() {
                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    timerElement.innerText = "00:00 (Expired)";
                    timerElement.classList.replace('text-[#1E3A8A]', 'text-red-500');
                    return;
                }

                let minutes = Math.floor(timeLeft / 60);
                let seconds = timeLeft % 60;

                timerElement.innerText =
                    (minutes < 10 ? "0" + minutes : minutes) + ":" +
                    (seconds < 10 ? "0" + seconds : seconds);
                timeLeft -= 1;
            }, 1000);
        });
    </script>
@endsection
