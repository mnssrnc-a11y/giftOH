@extends('layouts.dashboard')

@section('title', 'User Profile - Gift of Hope')

@section('content')
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
<a href="{{ route('logout') }}" onclick="event.preventDefault(); 
       document.getElementById('logout-form').submit();" class="...">
        Logout
    </a>
    <form id="logout-form" action="{{ route('logout') }}" method="POST" hidden>
        @csrf
    </form>


@endsection