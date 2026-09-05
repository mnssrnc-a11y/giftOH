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
                            <p class="text-2xl font-bold text-gray-900">5</p>
                            <p class="text-gray-500 text-xs mt-2">Unread messages</p>
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
                            <p class="text-2xl font-bold text-gray-900">12</p>
                            <p class="text-gray-500 text-xs mt-2">Active requests</p>
                        </div>
                        <svg class="w-10 h-10 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 12h10M7 17h6"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                        </svg>
                    </div>
                    <a href="{{ route('fund-request') }}" class="mt-4 inline-block w-full text-center bg-purple-500 text-white py-2 rounded-lg hover:bg-purple-600 transition-colors text-sm font-semibold">
                        View Requests
                    </a>
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
                            <!-- Notification Item 1 -->
                            <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">New Donation Received</p>
                                    <p class="text-gray-600 text-sm">You received a donation of $100 for Education Program</p>
                                    <p class="text-gray-500 text-xs mt-1">2 hours ago</p>
                                </div>
                            </div>

                            <!-- Notification Item 2 -->
                            <div class="flex items-start gap-4 p-4 bg-green-50 rounded-lg border-l-4 border-green-500">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Fund Request Approved</p>
                                    <p class="text-gray-600 text-sm">Your fund request for Healthcare Support has been approved</p>
                                    <p class="text-gray-500 text-xs mt-1">1 day ago</p>
                                </div>
                            </div>

                            <!-- Notification Item 3 -->
                            <div class="flex items-start gap-4 p-4 bg-yellow-50 rounded-lg border-l-4 border-yellow-500">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-yellow-500" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Profile Update Needed</p>
                                    <p class="text-gray-600 text-sm">Please update your profile information to improve your visibility</p>
                                    <p class="text-gray-500 text-xs mt-1">3 days ago</p>
                                </div>
                            </div>
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