@extends('layouts.dashboard')
@section('title', 'Update Profile - Gift of Hope')
@section('content')
    <div class="min-h-screen bg-gray-50 p-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white border-b border-gray-200 px-8 py-4 rounded-lg shadow-sm">
                <h1 class="text-2xl font-bold text-gray-900 mb-4">Update Profile</h1>
                <form action="{{ route('user.update') }}" method="POST" class="space-y-6">
                    @csrf
                    <div>
                        <label for="fname" class="block text-sm font-semibold text-gray-700 mb-2">First Name</label>
                        <input type="text" name="fname" id="fname" value="{{ Auth::user()->fname }}" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]" />
                    </div>
                    <div>
                        <label for="lname" class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                        <input type="text" name="lname" id="lname" value="{{ Auth::user()->lname }}" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]" />
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" id="email" value="{{ Auth::user()->email }}" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]" />
                    </div>
                    <button type="submit"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>