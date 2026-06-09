@extends('layouts.dashboard')

@section('title', 'Reports - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50">
        <div class="bg-white border-b border-gray-200 px-8 py-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Reports</h1>
                <p class="text-sm text-gray-500">Analytics, summaries, and downloadable reports</p>
            </div>
        </div>

        <div class="p-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-sm text-gray-500 mb-2">Total Raised (YTD)</div>
                    <div class="text-3xl font-bold text-gray-900">₱124,580</div>
                    <div class="text-sm text-green-600 mt-2">+12.5% vs last period</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-sm text-gray-500 mb-2">Avg Donation</div>
                    <div class="text-3xl font-bold text-gray-900">₱44</div>
                    <div class="text-sm text-gray-600 mt-2">Per donor</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-sm text-gray-500 mb-2">Verified Transactions</div>
                    <div class="text-3xl font-bold text-gray-900">1,284</div>
                    <div class="text-sm text-gray-600 mt-2">Smart box verified</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Donation Trends</h2>
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
                            <line x1="{{ $pad }}" y1="{{ $h - $pad }}" x2="{{ $w - $pad }}" y2="{{ $h - $pad }}" stroke="#E5E7EB" stroke-width="2" />
                            <line x1="{{ $pad }}" y1="{{ $pad }}" x2="{{ $pad }}" y2="{{ $h - $pad }}" stroke="#E5E7EB" stroke-width="2" />
                            <polyline points="{{ $poly }}" fill="none" stroke="#3B82F6" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            @foreach ($points as $p)
                                @php [$cx,$cy] = array_map('floatval', explode(',', $p)); @endphp
                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="5" fill="#3B82F6" />
                            @endforeach
                            @foreach ($trend as $i => $row)
                                @php $x = $pad + ($innerW * ($i / (count($trend) - 1))); @endphp
                                <text x="{{ $x }}" y="{{ $h - 10 }}" text-anchor="middle" font-size="12" fill="#6B7280">{{ $row['m'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Top Campaigns</h2>
                    @php
                        $campaigns = [
                            ['name' => 'Education Fund', 'raised' => 85000, 'goal' => 100000],
                            ['name' => 'Medical Support', 'raised' => 62500, 'goal' => 75000],
                            ['name' => 'Community Center', 'raised' => 42000, 'goal' => 50000],
                            ['name' => 'Food Program', 'raised' => 28000, 'goal' => 35000],
                        ];
                    @endphp
                    <div class="space-y-4">
                        @foreach ($campaigns as $campaign)
                            @php
                                $pct = min(100, (int) round(($campaign['raised'] / $campaign['goal']) * 100));
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="font-semibold text-gray-900">{{ $campaign['name'] }}</div>
                                    <div class="text-sm text-gray-600">₱{{ number_format($campaign['raised']) }} raised</div>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900">Recent Donations</h2>
                    <button class="px-4 py-2 rounded-lg bg-[#1E3A8A] text-white text-sm font-semibold hover:bg-[#2d4a9e]">Export</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Donor</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php
                                $donations = [
                                    ['donor' => 'John Doe', 'amount' => '₱15,000', 'type' => 'Bills', 'date' => '2026-05-14', 'status' => 'Verified'],
                                    ['donor' => 'Jane Smith', 'amount' => '₱12,500', 'type' => 'Bills', 'date' => '2026-05-13', 'status' => 'Verified'],
                                    ['donor' => 'Mike Johnson', 'amount' => '₱9,750', 'type' => 'Coins', 'date' => '2026-05-13', 'status' => 'Verified'],
                                ];
                            @endphp
                            @foreach ($donations as $donation)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $donation['donor'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ $donation['amount'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $donation['type'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $donation['date'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">{{ $donation['status'] }}</span>
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
