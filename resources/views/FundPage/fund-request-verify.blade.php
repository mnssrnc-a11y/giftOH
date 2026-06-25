@extends('layouts.dashboard')

@section('title', 'Verify Fund Request - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-8">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
                <div class="text-center mb-8">
                    <div class="bg-[#9333EA] w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Verify Transaction</h1>
                    <p class="text-gray-600">We sent a transaction verification code to <strong class="text-gray-900">{{ $email }}</strong></p>
                </div>

                <form method="POST" action="{{ route('fund-request.verify') }}">
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
                        <input id="code" name="code" type="text" required autofocus maxlength="6" placeholder="000000" class="w-full px-4 py-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9333EA] text-center text-2xl font-mono tracking-[0.5em] font-bold"/>
                    </div>

                    <button type="submit" class="w-full bg-[#9333EA] text-white py-3 rounded-lg font-bold hover:bg-[#7e22ce] transition-colors flex items-center justify-center gap-2">Verify & Submit Request</button>
                </form>

                <div class="mt-6 text-center space-y-2">
                    <p class="text-sm text-gray-600">
                        Didn't receive the code?
                        <form method="POST" action="{{ route('fund-request.resend-code') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-[#9333EA] font-semibold hover:underline bg-transparent border-0 p-0 cursor-pointer">Resend Code</button>
                        </form>
                    </p>
                    <p class="text-sm text-gray-500">
                        <a href="{{ route('fund-request') }}" class="text-gray-500 hover:underline">← Cancel Transaction</a>
                    </p>
                </div>
            </div>
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500">
                    The code expires in <span id="countdown-timer" class="font-bold text-[#9333EA]">05:00</span>. Check your spam folder if you don't see it.
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
                    timerElement.classList.replace('text-[#9333EA]', 'text-red-500');
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
