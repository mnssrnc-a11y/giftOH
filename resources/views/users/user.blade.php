@extends('layouts.dashboard')

@section('title', 'User Profile - Gift of Hope')




@section('content')
    <div class="min-h-screen bg-gray-50 p-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header Section -->
            <div class="bg-white border-b border-gray-200 px-8 py-4">
            <div class="flex items-center justify-between">
                <div>
                                <h1 class="text-4xl font-bold text-gray-900 mb-2">Welcome, {{ Auth::user()->fname }} {{ Auth::user()->lname }}!</h1>
                <p class="text-gray-600"></p>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative hidden md:block">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3"/>
                            <circle cx="11" cy="11" r="7"/>
                        </svg>
                        <input
                            class="w-72 pl-10 pr-4 py-2 border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            aria-label="Search"
                        />
                    </div>
                    <a href="{{ route('user') }}" class="w-10 h-10 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 flex items-center justify-center" aria-label="Profile">
                        <svg class="w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 1 0-16 0"/>
                            <circle cx="12" cy="8" r="3"/>
                        </svg>

                    </a>
                </div>
            </div>
        </div>

<a href="{{ route('logout') }}" onclick="event.preventDefault();
       document.getElementById('logout-form').submit();" class="...">
        Logout
    </a>
    <form id="logout-form" action="{{ route('logout') }}" method="POST" hidden>
        @csrf
    </form>


@endsection