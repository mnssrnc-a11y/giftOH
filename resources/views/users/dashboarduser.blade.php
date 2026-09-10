@extends('layouts.dashboard')

@section('title', 'Dashboard - Gift of Hope')

@section('content')
            <!-- Top Stats Row -->
            <div class="flex items-center grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- User Profile Card -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#3B82F6]">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-semibold mb-2">Profile</p>
                            <p class="text-2xl font-bold text-gray-900">{{ Auth::user()->name }}</p>
                            <p class="text-gray-500 text-xs mt-2">{{ Auth::user()->email }}</p>
                        </div>
                        <svg class="w-10 h-10 text-[#3B82F6]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <a href="{{ route('user') }}" class="mt-4 inline-block w-full text-center bg-[#3B82F6] text-white py-2 rounded-lg hover:bg-[#2563EB] transition-colors text-sm font-semibold">
                        View Profile
                    </a>
                </div>

                <!-- Notifications Card -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-semibold mb-2">Notifications</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $notificationCount }}</p>
                            <p class="text-gray-500 text-xs mt-2">Fund request notifications</p>
                        </div>
                        <svg class="w-10 h-10 text-yellow-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </div>
                    <a href="{{ route('user') }}" class="mt-4 inline-block w-full text-center bg-yellow-500 text-white py-2 rounded-lg hover:bg-yellow-600 transition-colors text-sm font-semibold">
                        View Notifications
                    </a>
                </div>

                <!-- Fund Requests -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-semibold mb-2">Fund Requests</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $fundRequestCount }}</p>
                            <p class="text-gray-500 text-xs mt-2">Active requests</p>
                        </div>
                        <svg class="w-10 h-10 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 12h10M7 17h6"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                        </svg>
                    </div>
                    <button type="button" data-fund-requests-open class="mt-4 inline-block w-full text-center bg-purple-500 text-white py-2 rounded-lg hover:bg-purple-600 transition-colors text-sm font-semibold">
                        View Requests
                    </button>
                </div>
            </div>

            <div id="fund-requests-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="fund-requests-title">
                <div class="w-full max-w-5xl rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <div>
                            <h2 id="fund-requests-title" class="text-xl font-bold text-gray-900">My Fund Requests</h2>
                            <p class="text-sm text-gray-500">Select a request to view its submitted details.</p>
                        </div>
                        <button type="button" data-fund-requests-close class="text-2xl leading-none text-gray-500 hover:text-gray-900" aria-label="Close fund requests">&times;</button>
                    </div>

                    <div class="max-h-[65vh] overflow-auto p-6">
                        @if (count($fundRequests))
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px] text-left text-sm">
                                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500">
                                        <tr>
                                            <th class="px-4 py-3">Organization</th>
                                            <th class="px-4 py-3">Category</th>
                                            <th class="px-4 py-3">Amount</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($fundRequests as $fundRequest)
                                            @php
                                                $status = $fundRequest['status_name'] ?? $fundRequest['status'] ?? 'Pending';
                                            @endphp
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-4 font-semibold text-gray-900">{{ $fundRequest['org_name'] ?? 'Unnamed organization' }}</td>
                                                <td class="px-4 py-4 text-gray-600">{{ $fundRequest['category'] ?? $fundRequest['category_name'] ?? 'N/A' }}</td>
                                                <td class="px-4 py-4 text-gray-600">₱{{ number_format((float) ($fundRequest['amount_requested'] ?? 0), 2) }}</td>
                                                <td class="px-4 py-4 capitalize text-gray-600">{{ $status }}</td>
                                                <td class="px-4 py-4">
                                                    <a href="{{ route('fund-request.show', $fundRequest['id']) }}" class="font-semibold text-blue-600 hover:underline">View details</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="py-8 text-center text-sm text-gray-500">You have not submitted any fund requests yet.</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column - Recent Activity -->
                <div class="lg:col-span-2">
                    <!-- User Account Section -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-900">Account Information</h2>
                            <a href="{{ route('user') }}" class="flex items-center gap-2 px-4 py-2 bg-[#3B82F6] text-white rounded-lg hover:bg-[#2563EB] transition-colors text-sm font-semibold">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Edit Profile
                            </a>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="border-b pb-4">
                                    <p class="text-gray-600 text-sm">Full Name</p>
                                    <p class="text-gray-900 font-semibold">{{ Auth::user()->fname }} {{ Auth::user()->lname }}</p>
                                </div>
                                <div class="border-b pb-4">
                                    <p class="text-gray-600 text-sm">Email Address</p>
                                    <p class="text-gray-900 font-semibold">{{ Auth::user()->email }}</p>
                                </div>
                                <div class="border-b pb-4">
                                    <p class="text-gray-600 text-sm">Member Since</p>
                                    <p class="text-gray-900 font-semibold">{{ Auth::user()->created_at }}</p>
                                </div>
                                <div class="border-b pb-4">
                                    <p class="text-gray-600 text-sm">Account Status</p>
                                    <p class="text-green-600 font-semibold">Active</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications Activity Section -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-900">Latest Notifications</h2>
                            <a href="{{ route('user') }}" class="flex items-center gap-2 px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors text-sm font-semibold">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                View All
                            </a>
                        </div>

                        <div class="space-y-4">
                            @forelse ($notifications as $notification)
                                @php($isApproved = in_array($notification['type'], ['approved', 'funding_approved'], true))
                                <form method="POST" action="{{ route('notifications.read', $notification['id']) }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-start gap-4 p-4 text-left {{ $isApproved ? 'bg-green-50 border-green-500' : 'bg-red-50 border-red-500' }} rounded-lg border-l-4 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 {{ $isApproved ? 'text-green-500' : 'text-red-500' }}" viewBox="0 0 24 24" fill="currentColor">
                                                @if ($isApproved)
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                                @else
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                                @endif
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <p class="font-semibold text-gray-900">{{ $notification['label'] }}</p>
                                            <p class="text-gray-600 text-sm">{{ $notification['message'] }}</p>
                                            @if ($notification['created_at'])
                                                <p class="text-gray-500 text-xs mt-1">{{ \Carbon\Carbon::parse($notification['created_at'])->diffForHumans() }}</p>
                                            @endif
                                        </div>
                                    </button>
                                </form>
                            @empty
                                <p class="text-gray-500 text-sm">No approval or denial notifications yet.</p>
                            @endforelse
                        </div>

                        <!-- View All Notifications Button -->
                        <div class="mt-6 text-center">
                            <a href="{{ route('user') }}" class="inline-block px-6 py-2 border-2 border-yellow-500 text-yellow-500 rounded-lg hover:bg-yellow-50 transition-colors font-semibold">
                                View All Notifications →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Quick Actions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    Quick Actions
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">Quick Actions</h2>

                        <div class="space-y-3">
                            <a href="{{ route('user') }}" class="flex items-center gap-3 p-4 bg-[#3B82F6] text-white rounded-lg hover:bg-[#2563EB] transition-colors">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span class="font-semibold">My Profile</span>
                            </a>

                            <a href="{{ route('user') }}" class="flex items-center gap-3 p-4 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <span class="font-semibold">Notifications</span>
                            </a>

                            <a href="{{ route('fund-request') }}" class="flex items-center gap-3 p-4 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 12h10M7 17h6"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                                </svg>
                                <span class="font-semibold">Fund Request</span>
                            </a>

                            <a href="{{ route('settings') }}" class="flex items-center gap-3 p-4 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a7.9 7.9 0 0 0 .1-2l2-1.2-2-3.4-2.3.7a7.7 7.7 0 0 0-1.7-1l-.3-2.4h-4l-.3 2.4a7.7 7.7 0 0 0-1.7 1L6.9 8.4l-2 3.4 2 1.2a7.9 7.9 0 0 0 .1 2l-2 1.2 2 3.4 2.3-.7a7.7 7.7 0 0 0 1.7 1l.3 2.4h4l.3-2.4a7.7 7.7 0 0 0 1.7-1l2.3.7 2-3.4-2-1.2z"/>
                                </svg>
                                <span class="font-semibold">Settings</span>
                            </a>
                        </div>
                    </div>

                    Statistics Card
                    <div class="bg-gradient-to-br from-[#3B82F6] to-[#1E3A8A] rounded-lg shadow-md p-6 text-white">
                        <h2 class="text-xl font-bold mb-4">Your Impact</h2>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-blue-100">Total Donated</span>
                                <span class="text-2xl font-bold">$5,340</span>
                            </div>
                            <div class="border-t border-blue-400 pt-4">
                                <p class="text-blue-100 text-sm">Thank you for making a difference in people's lives!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('fund-requests-modal');
        const openButton = document.querySelector('[data-fund-requests-open]');
        const closeButton = document.querySelector('[data-fund-requests-close]');

        if (!modal || !openButton || !closeButton) {
            return;
        }

        const closeModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        openButton.addEventListener('click', () => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });

        closeButton.addEventListener('click', closeModal);

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    });
</script>