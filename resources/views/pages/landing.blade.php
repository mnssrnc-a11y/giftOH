@extends('layouts.public')

@section('title', 'Gift of Hope')

@section('content')
    <div class="min-h-screen bg-white">
        <section class="bg-gradient-to-br from-[#1E3A8A] to-[#3B82F6] text-white py-20 px-8">
            <div class="max-w-6xl mx-auto">
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div>
                        <h1 class="text-5xl font-bold mb-6">Give Hope, Change Lives</h1>
                        <p class="text-xl text-blue-100 mb-8">
                            A transparent charity donation platform with smart IoT monitoring.
                            Every donation is tracked, verified, and makes a real difference.
                        </p>
                        <div class="flex gap-4">
                            <a href="{{ route('login ') }}"
                                class="bg-transparent border-2 border-white px-8 py-3 rounded-lg font-semibold hover:bg-white/10 transition-colors">
                                Sign In
                            </a>
                            <a href="{{ route('register') }}"
                                class="bg-white text-[#1E3A8A] px-8 py-3 rounded-lg font-semibold hover:bg-blue-50 transition-colors">
                                Sign Up
                            </a>
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
                <a href="{{ route('donations') }}"
                    class="inline-block bg-white text-[#1E3A8A] px-12 py-4 rounded-lg font-semibold text-lg hover:bg-blue-50 transition-colors">
                    Start Donating Today
                </a>
            </div>
        </section>
    </div>
@endsection

