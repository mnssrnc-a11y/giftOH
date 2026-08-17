<?php
if ($user = auth()->user()) {
    $role = $user->role;
    if ($role === 'admin') {
        $layout = 'layouts.admin';
    } else {
        $layout = 'layouts.dashboard';
    }
} else {
    $layout = 'layouts.dashboard';
}
?>
@extends($layout)
@section('title', 'IoT Box Monitor - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50">
        <div class="bg-white border-b border-gray-200 px-8 py-4">
            <h1 class="text-2xl font-bold text-gray-900">IoT Box Monitor</h1>
            <p class="text-sm text-gray-500">Real-time status of smart donation boxes</p>
        </div>

        <div class="p-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-sm text-gray-500 mb-2">Active Boxes</div>
                    <div class="text-3xl font-bold text-gray-900">12</div>
                    <div class="text-sm text-green-600 mt-2">All systems operational</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-sm text-gray-500 mb-2">Today's Total</div>
                    <div class="text-3xl font-bold text-gray-900">₱3,420</div>
                    <div class="text-sm text-gray-600 mt-2">Verified donations</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-sm text-gray-500 mb-2">Alerts</div>
                    <div class="text-3xl font-bold text-gray-900">0</div>
                    <div class="text-sm text-gray-600 mt-2">No issues detected</div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Boxes</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Box ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Last Seen</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Collected</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php
                                $boxes = [
                                    ['id' => 'SB-001', 'location' => 'Main Lobby', 'status' => 'Online', 'seen' => 'Just now', 'total' => '₱18,430'],
                                    ['id' => 'SB-002', 'location' => 'Entrance Gate', 'status' => 'Online', 'seen' => '2 min ago', 'total' => '₱12,115'],
                                    ['id' => 'SB-003', 'location' => 'Community Center', 'status' => 'Online', 'seen' => '5 min ago', 'total' => '₱9,870'],
                                ];
                            @endphp

                            @foreach ($boxes as $box)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $box['id'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $box['location'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">{{ $box['status'] }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $box['seen'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ $box['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    Note: This is currently a UI prototype (no Arduino/IoT feed wired yet).
                </p>
            </div>
        </div>
    </div>
@endsection

