@extends('layouts.dashboard')

@section('title', 'Dashboard - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50">
        <div class="bg-white border-b border-gray-200 px-8 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-sm text-gray-500">Welcome back! Here's your donation overview.</p>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative hidden md:block">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3"/>
                            <circle cx="11" cy="11" r="7"/>
                        </svg>
                        <input
                            class="w-72 pl-10 pr-4 py-2 border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            aria-label="Search"
                        />
                    </div>
                    <a href="{{ route('user') }}" class="w-10 h-10 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 flex items-center justify-center" aria-label="Profile">
                        <svg class="w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 1 0-16 0"/>
                            <circle cx="12" cy="8" r="3"/>
                        </svg>
                      
                    </a>                    
                </div>
            </div>
        </div>

        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-blue-500 p-3 rounded-lg">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 1v22"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 5H10a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6H7"/>
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-green-600">+12.5%</span>
                    </div>
                    <div class="text-2xl font-bold text-gray-900 mb-1">₱124,580</div>
                    <div class="text-sm text-gray-500">Total Donations</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-green-500 p-3 rounded-lg">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 7h7v7"/>
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-green-600">+8.2%</span>
                    </div>
                    <div class="text-2xl font-bold text-gray-900 mb-1">₱3,420</div>
                    <div class="text-sm text-gray-500">Today's Donations</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-purple-500 p-3 rounded-lg">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 1 0-16 0"/>
                                <circle cx="12" cy="8" r="3"/>
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-green-600">+156</span>
                    </div>
                    <div class="text-2xl font-bold text-gray-900 mb-1">2,847</div>
                    <div class="text-sm text-gray-500">Total Donors</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-orange-500 p-3 rounded-lg">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8l-9-5-9 5 9 5 9-5z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8v8l9 5 9-5V8"/>
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-green-600">100%</span>
                    </div>
                    <div class="text-2xl font-bold text-gray-900 mb-1">12</div>
                    <div class="text-sm text-gray-500">Active Smart Boxes</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900 mb-6">Donation Trends</h3>
                    @php
                        $trend = [
                            ['m' => 'Jan', 'v' => 8500],
                            ['m' => 'Feb', 'v' => 9200],
                            ['m' => 'Mar', 'v' => 10500],
                            ['m' => 'Apr', 'v' => 11200],
                            ['m' => 'May', 'v' => 12800],
                            ['m' => 'Jun', 'v' => 14500],
                        ];
                        $maxV = collect($trend)->max('v');
                        $points = [];
                        $w = 560; $h = 240; $pad = 30;
                        $innerW = $w - ($pad * 2);
                        $innerH = $h - ($pad * 2);
                        foreach ($trend as $i => $row) {
                            $x = $pad + ($innerW * ($i / (count($trend) - 1)));
                            $y = $pad + ($innerH * (1 - ($row['v'] / $maxV)));
                            $points[] = sprintf('%.1f,%.1f', $x, $y);
                        }
                        $poly = implode(' ', $points);
                    @endphp
                    <div class="bg-gray-50 rounded-lg border border-gray-100 p-4">
                        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-64">
                            <rect x="0" y="0" width="{{ $w }}" height="{{ $h }}" fill="transparent" />
                            <line x1="{{ $pad }}" y1="{{ $h - $pad }}" x2="{{ $w - $pad }}" y2="{{ $h - $pad }}" stroke="#E5E7EB" stroke-width="2" />
                            <line x1="{{ $pad }}" y1="{{ $pad }}" x2="{{ $pad }}" y2="{{ $h - $pad }}" stroke="#E5E7EB" stroke-width="2" />
                            <polyline points="{{ $poly }}" fill="none" stroke="#3B82F6" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            @foreach ($points as $p)
                                @php [$cx,$cy] = array_map('floatval', explode(',', $p)); @endphp
                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="5" fill="#3B82F6" />
                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="9" fill="#3B82F6" opacity="0.12" />
                            @endforeach
                            @foreach ($trend as $i => $row)
                                @php
                                    $x = $pad + ($innerW * ($i / (count($trend) - 1)));
                                @endphp
                                <text x="{{ $x }}" y="{{ $h - 10 }}" text-anchor="middle" font-size="12" fill="#6B7280">{{ $row['m'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900 mb-6">Donation Types</h3>
                    @php
                        $coins = 35;
                        $bills = 65;
                        $r = 70;
                        $circ = 2 * pi() * $r;
                        $billsLen = $circ * ($bills / 100);
                        $coinsLen = $circ * ($coins / 100);
                    @endphp
                    <div class="bg-gray-50 rounded-lg border border-gray-100 p-6 flex items-center justify-center">
                        <div class="flex items-center gap-8">
                            <svg width="200" height="200" viewBox="0 0 200 200">
                                <g transform="translate(100,100) rotate(-90)">
                                    <circle r="{{ $r }}" cx="0" cy="0" fill="none" stroke="#E5E7EB" stroke-width="22" />
                                    <circle r="{{ $r }}" cx="0" cy="0" fill="none" stroke="#1E3A8A" stroke-width="22" stroke-linecap="round"
                                        stroke-dasharray="{{ round($billsLen, 2) }} {{ round($circ - $billsLen, 2) }}" stroke-dashoffset="0" />
                                    <circle r="{{ $r }}" cx="0" cy="0" fill="none" stroke="#3B82F6" stroke-width="22" stroke-linecap="round"
                                        stroke-dasharray="{{ round($coinsLen, 2) }} {{ round($circ - $coinsLen, 2) }}" stroke-dashoffset="{{ -round($billsLen, 2) }}" />
                                </g>
                                <text x="100" y="98" text-anchor="middle" font-size="20" fill="#111827" font-weight="700">{{ $bills }}%</text>
                                <text x="100" y="122" text-anchor="middle" font-size="12" fill="#6B7280">Bills</text>
                            </svg>

                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <span class="inline-block w-3 h-3 rounded-full bg-[#1E3A8A]"></span>
                                    <span class="text-sm font-semibold text-gray-700">Bills</span>
                                    <span class="text-sm text-gray-500">{{ $bills }}%</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="inline-block w-3 h-3 rounded-full bg-[#3B82F6]"></span>
                                    <span class="text-sm font-semibold text-gray-700">Coins</span>
                                    <span class="text-sm text-gray-500">{{ $coins }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">Recent Donations</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php
                                $recentDonations = [
                                    ['id' => 'DN-001', 'date' => '2026-05-14', 'time' => '10:30 AM', 'amount' => '₱50.00', 'type' => 'Bills', 'status' => 'Verified'],
                                    ['id' => 'DN-002', 'date' => '2026-05-14', 'time' => '09:45 AM', 'amount' => '₱12.50', 'type' => 'Coins', 'status' => 'Verified'],
                                    ['id' => 'DN-003', 'date' => '2026-05-14', 'time' => '09:15 AM', 'amount' => '₱100.00', 'type' => 'Bills', 'status' => 'Verified'],
                                    ['id' => 'DN-004', 'date' => '2026-05-13', 'time' => '05:20 PM', 'amount' => '₱25.00', 'type' => 'Bills', 'status' => 'Verified'],
                                    ['id' => 'DN-005', 'date' => '2026-05-13', 'time' => '04:30 PM', 'amount' => '₱8.75', 'type' => 'Coins', 'status' => 'Verified'],
                                    ['id' => 'DN-006', 'date' => '2026-05-13', 'time' => '03:15 PM', 'amount' => '₱75.00', 'type' => 'Bills', 'status' => 'Verified'],
                                ];
                            @endphp

                            @foreach ($recentDonations as $donation)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $donation['id'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $donation['date'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $donation['time'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ $donation['amount'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $donation['type'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ $donation['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
