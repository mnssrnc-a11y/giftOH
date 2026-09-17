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
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                        <p class="block text-gray-900 text-lg font-semibold ">{{ Auth::user()->lname }} {{ Auth::user()->fname }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Contact Number</label>
                        <p class="text-gray-900 text-lg font-semibold">{{ Auth::user()->phone }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <p class="text-gray-900 text-lg font-semibold">{{ Auth::user()->email }}</p>
                        <a href="{{ route('user.edit') }}" class="inline-block mt-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-200">Edit Profile</a>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Preferences</h2>
                <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                    @csrf
                    <label class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">Email Notifications</span>
                        <input
                            type="checkbox"
                            name="email_notifications"
                            value="1"
                            class="w-5 h-5"
                            {{ filter_var(Auth::user()->email_notifications ?? true, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                        />
                    </label>
                    <label class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">Dark Mode</span>
                        <input
                            type="checkbox"
                            name="dark_mode"
                            value="1"
                            class="w-5 h-5"
                            {{ filter_var(Auth::user()->dark_mode ?? false, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                        />
                    </label>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Save Preferences
                    </button>
                </form>
                @if (session('status'))
                    <p class="text-sm text-green-700 mt-4">{{ session('status') }}</p>
                @endif
            </div>
        </div>
    </div>
@endsection

