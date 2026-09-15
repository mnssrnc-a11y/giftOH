@extends('layouts.dashboard')

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
                    <div class="text-3xl font-bold text-gray-900" id="stat-active-boxes">-</div>
                    <div class="text-sm text-green-600 mt-2">All systems operational</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-sm text-gray-500 mb-2">Total Collected</div>
                    <div class="text-3xl font-bold text-gray-900" id="stat-today-total">-</div>
                    <div class="text-sm text-gray-600 mt-2">Verified donations</div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-sm text-gray-500 mb-2">Alerts</div>
                    <div class="text-3xl font-bold text-gray-900" id="stat-alerts">-</div>
                    <div class="text-sm text-gray-600 mt-2">Offline boxes</div>
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
                        <tbody id="boxes-tbody" class="divide-y divide-gray-100">
                            <tr><td colspan="5" class="px-6 py-4 text-sm text-gray-400">Loading live data…</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    Connected live to Firebase Realtime Database.
                </p>
            </div>
        </div>
    </div>

    @vite('resources/js/iot-monitor.js')
@endsection
