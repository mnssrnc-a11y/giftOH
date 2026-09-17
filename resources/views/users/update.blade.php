@extends('layouts.dashboard')
@section('title', 'Update Profile - Gift of Hope')
@section('content')
    <div class="min-h-screen bg-gray-50 p-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white border-b border-gray-200 px-8 py-4 rounded-lg shadow-sm">
                <h1 class="text-2xl font-bold text-gray-900 mb-4">Update Profile</h1>
                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        @foreach ($errors->all() as $error)
                            <p class="text-sm text-red-700">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
                <form action="{{ route('user.update') }}" method="POST" class="space-y-6">
                    @csrf
                    <div>
                        <label for="fname" class="block text-sm font-semibold text-gray-700 mb-2">First Name</label>
                        <input type="text" name="fname" id="fname" value="{{ old('fname', Auth::user()->fname) }}" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]" />
                    </div>
                    <div>
                        <label for="lname" class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                        <input type="text" name="lname" id="lname" value="{{ old('lname', Auth::user()->lname) }}" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]" />
                    </div>
                    <div>
                        <label for="contact_number" class="block text-sm font-semibold text-gray-700 mb-2">Contact Number</label>
                        <input type="text" name="contact_number" id="contact_number" value="{{ old('contact_number', Auth::user()->phone) }}" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]" />
                    </div>
                    <button type="submit"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        Update Profile
                    </button>
                    <button type="button" onclick="window.location='{{ route('settings') }}'"
                            class="px-6 py-3 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-200">
                        cancel
                    </button>
                </form>
            </div>
        </div>
    </div>