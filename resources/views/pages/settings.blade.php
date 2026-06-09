@extends('layouts.dashboard')

@section('title', 'Settings - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50">
        <div class="bg-white border-b border-gray-200 px-8 py-4">
            <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
            <p class="text-sm text-gray-500">Manage your preferences and organization settings</p>
        </div>

        <div class="p-8 space-y-6 max-w-4xl">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Profile</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Name</label>
                        <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]" value="Admin User" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <input type="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]" value="admin@giftofhope.org" />
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Preferences</h2>
                <div class="space-y-4">
                    <label class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">Email Notifications</span>
                        <input type="checkbox" class="w-5 h-5" checked />
                    </label>
                    <label class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">SMS Alerts</span>
                        <input type="checkbox" class="w-5 h-5" />
                    </label>
                    <label class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">Dark Mode</span>
                        <input type="checkbox" class="w-5 h-5" />
                    </label>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    Note: This is currently a UI prototype (no preference persistence wired yet).
                </p>
            </div>
        </div>
    </div>
@endsection

