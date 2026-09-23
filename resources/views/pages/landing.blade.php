@extends('layouts.public')

@section('title', 'Gift of Hope')

@section('content')
    @if (session('alert_error'))
        <div class="mx-auto max-w-6xl px-8 pt-6">
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                {{ session('alert_error') }}
            </div>
        </div>
    @endif
    <div class="min-h-screen bg-white">
        <section class="bg-gradient-to-br from-[#1E3A8A] to-[#3B82F6] text-white py-20 px-8">
            <div class="max-w-6xl mx-auto">
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div>
                        <h1 class="text-5xl font-bold mb-6">Give Hope, Change Lives</h1>
                        <p class="text-xl text-blue-100 mb-8">
                            A transparent charity donation platform with smart IoT monitoring.
                            Every donation is tracked, verified, and makes a real difference.
                        </p>                        <div class="flex flex-col items-start gap-4">
                            <button type="button" id="start-button" aria-controls="account-choice" aria-haspopup="dialog"
                                class="bg-white text-[#1E3A8A] px-8 py-3 rounded-lg font-semibold hover:bg-blue-50 transition-colors">
                                Start
                            </button>
                        </div>
                    </div>

                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20">
                        <div class="bg-white rounded-lg p-6 shadow-xl">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="bg-[#3B82F6] text-white p-3 rounded-lg w-14 h-14"></div>
                                <div>
                                    <div class="text-sm text-gray-500">Smart Donation Box</div>
                                    <div class="text-xl font-bold text-gray-900">IoT Enabled</div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-gray-700 text-sm">
                                <div class="bg-green-50 p-3 rounded-lg">
                                    <div class="text-green-600 font-semibold">UV Sensor</div>
                                    <div class="text-xs">Active</div>
                                </div>
                                <div class="bg-green-50 p-3 rounded-lg">
                                    <div class="text-green-600 font-semibold">IR Sensor</div>
                                    <div class="text-xs">Active</div>
                                </div>
                                <div class="bg-green-50 p-3 rounded-lg">
                                    <div class="text-green-600 font-semibold">Magnetic</div>
                                    <div class="text-xs">Active</div>
                                </div>
                                <div class="bg-green-50 p-3 rounded-lg">
                                    <div class="text-green-600 font-semibold">Weight</div>
                                    <div class="text-xs">Active</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-20 px-8 bg-gray-50">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Why Choose Gift of Hope?</h2>
                    <p class="text-xl text-gray-600">Cutting-edge technology meets compassionate giving</p>
                </div>

                <div class="grid md:grid-cols-3 gap-8">
                    @php
                        $features = [
                            [
                                'title' => 'Complete Transparency',
                                'description' => 'Track every donation in real-time with full visibility into where your contributions go.',
                            ],
                            [
                                'title' => 'Real-Time Tracking',
                                'description' => 'Monitor donations as they happen with our advanced IoT-enabled smart donation boxes.',
                            ],
                            [
                                'title' => 'Smart Automation',
                                'description' => 'Automated coin and bill detection with UV, IR, magnetic, and weight sensors.',
                            ],
                        ];
                    @endphp

                    @foreach ($features as $feature)
                        <div class="bg-white p-8 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="bg-[#3B82F6] text-white w-14 h-14 rounded-lg mb-4"></div>
                            <h3 class="text-xl font-bold text-gray-900 mb-3">{{ $feature['title'] }}</h3>
                            <p class="text-gray-600">{{ $feature['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="py-20 px-8 bg-[#1E3A8A] text-white">
            <div class="max-w-4xl mx-auto text-center">
                <div class="w-16 h-16 mx-auto mb-6 rounded-full bg-white/10"></div>
                <h2 class="text-4xl font-bold mb-6">Ready to Make a Difference?</h2>
                <p class="text-xl text-blue-100 mb-8">
                    Join thousands of donors who trust our platform to deliver hope to those in need.
                </p>
                <a href="#top" id="start-button" aria-controls="account-choice" aria-haspopup="dialog"
                    class="inline-block bg-white text-[#1E3A8A] px-12 py-4 rounded-lg font-semibold text-lg hover:bg-blue-50 transition-colors">
                    join us now
                </a>
            </div>
        </section>
    </div>
    
    <div id="account-choice" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 px-6" role="dialog" aria-modal="true" aria-labelledby="account-choice-title">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-2xl">
            <h2 id="account-choice-title" class="mb-3 text-2xl font-bold text-gray-900">Do you have an account?</h2>
            <p class="mb-6 text-gray-600">Choose how you would like to continue.</p>
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('login') }}" class="rounded-lg bg-[#1E3A8A] px-6 py-3 font-semibold text-white hover:bg-[#2d4a9e]">Yes</a>
                <a href="{{ route('register') }}" class="rounded-lg border border-[#1E3A8A] px-6 py-3 font-semibold text-[#1E3A8A] hover:bg-blue-50">No</a>
            </div>
            <button type="button" id="close-account-choice" class="mt-5 text-sm text-gray-500 underline hover:text-gray-700">Cancel</button>
        </div>
    </div>
    <script>
        const accountChoice = document.getElementById('account-choice');
        const startButton = document.getElementById('start-button');
        const closeAccountChoice = document.getElementById('close-account-choice');

        startButton?.addEventListener('click', function () {
            accountChoice?.classList.remove('hidden');
            accountChoice?.classList.add('flex');
        });

        closeAccountChoice?.addEventListener('click', function () {
            accountChoice?.classList.add('hidden');
            accountChoice?.classList.remove('flex');
        });

        accountChoice?.addEventListener('click', function (event) {
            if (event.target === accountChoice) {
                closeAccountChoice?.click();
            }
        });
    </script>
@endsection