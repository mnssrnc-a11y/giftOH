@extends('layouts.dashboard')

@section('title', 'About - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-white">
        <div class="bg-gradient-to-r from-[#1E3A8A] to-[#3B82F6] text-white px-8 py-16">
            <div class="max-w-5xl mx-auto text-center">
                <h1 class="text-5xl font-bold mb-6">About Gift of Hope</h1>
                <p class="text-xl text-blue-100 max-w-3xl mx-auto">
                    A revolutionary charity platform combining transparency, technology, and compassion
                    to transform how we give and receive help.
                </p>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-8 py-16">
            <div class="grid md:grid-cols-2 gap-12 mb-16">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Our Vision</h2>
                    <p class="text-lg text-gray-700 leading-relaxed">
                        To create a world where charitable giving is transparent, efficient, and impactful.
                        We envision a future where every donation is tracked, every peso is accounted for,
                        and every person in need receives the help they deserve.
                    </p>
                </div>

                <div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Our Mission</h2>
                    <p class="text-lg text-gray-700 leading-relaxed">
                        To leverage IoT technology and smart donation boxes to provide complete transparency
                        in charitable giving. We're committed to building trust between donors and beneficiaries
                        through real-time tracking, verification, and accountability.
                    </p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-2xl p-12 mb-16">
                <h2 class="text-3xl font-bold text-gray-900 text-center mb-12">Our Impact</h2>
                @php
                    $stats = [
                        ['value' => '₱2.5M+', 'label' => 'Total Donations'],
                        ['value' => '15K+', 'label' => 'Donors Worldwide'],
                        ['value' => '50+', 'label' => 'Communities Served'],
                        ['value' => '100%', 'label' => 'Transparency'],
                    ];
                @endphp
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                    @foreach ($stats as $stat)
                        <div class="text-center">
                            <div class="text-4xl font-bold text-[#1E3A8A] mb-2">{{ $stat['value'] }}</div>
                            <div class="text-sm text-gray-600">{{ $stat['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            @php
                $values = [
                    [
                        'title' => 'Transparency',
                        'description' => 'Every donation is tracked and verified through our IoT-enabled smart boxes, ensuring complete accountability.',
                    ],
                    [
                        'title' => 'Impact',
                        'description' => 'We focus on creating meaningful change in the lives of those who need it most, one donation at a time.',
                    ],
                    [
                        'title' => 'Community',
                        'description' => 'Building a network of compassionate donors and beneficiaries working together for a better tomorrow.',
                    ],
                    [
                        'title' => 'Innovation',
                        'description' => 'Leveraging cutting-edge IoT technology to revolutionize charitable giving and donation tracking.',
                    ],
                ];
            @endphp

            <div>
                <h2 class="text-3xl font-bold text-gray-900 text-center mb-12">Our Core Values</h2>
                <div class="grid md:grid-cols-2 gap-8">
                    @foreach ($values as $value)
                        <div class="bg-white border border-gray-200 rounded-xl p-8 hover:shadow-lg transition-shadow">
                            <div class="flex items-start gap-4">
                                <div class="bg-[#3B82F6] p-3 rounded-lg flex-shrink-0 w-12 h-12"></div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ $value['title'] }}</h3>
                                    <p class="text-gray-600 leading-relaxed">{{ $value['description'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-gradient-to-r from-[#1E3A8A] to-[#3B82F6] rounded-2xl p-12 mt-16 text-white text-center">
                <div class="w-16 h-16 mx-auto mb-6 rounded-full bg-white/10"></div>
                <h2 class="text-3xl font-bold mb-4">The Technology Behind the Mission</h2>
                <p class="text-xl text-blue-100 max-w-3xl mx-auto">
                    Our smart donation boxes use UV sensors for bill authentication, IR sensors for coin detection,
                    magnetic sensors for metal verification, and weight sensors for amount calculation.
                    Every transaction is logged in real-time, ensuring complete transparency and trust.
                </p>
            </div>
        </div>
    </div>
@endsection

