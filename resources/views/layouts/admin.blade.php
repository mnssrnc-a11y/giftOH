@extends('app')

@section('body')
    <div class="flex h-screen bg-gray-50">
        <aside class="w-64 bg-[#1E3A8A] text-white flex flex-col">
            <div class="p-6 border-b border-blue-700">
                <a href="{{ route('landing') }}" class="block">
                    <div class="text-2xl font-bold">Gift of Hope</div>
                    <div class="text-blue-200 text-sm mt-1">Charity Platform</div>
                </a>
            </div>

            <nav class="flex-1 p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('landing') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-blue-100 hover:bg-blue-800 {{ request()->routeIs('landing') ? 'bg-[#3B82F6] text-white hover:bg-[#3B82F6]' : '' }}">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V21a1.5 1.5 0 0 1-1.5 1.5H4.5A1.5 1.5 0 0 1 3 21v-10.5z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 22.5V15a3 3 0 0 1 6 0v7.5"/>
                            </svg>
                            <span class="text-sm font-semibold">Home</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('about') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-blue-100 hover:bg-blue-800 {{ request()->routeIs('about') ? 'bg-[#3B82F6] text-white hover:bg-[#3B82F6]' : '' }}">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="9" />
                                <path stroke-linecap="round" d="M12 10.5h.01" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 12h1v4h1" />
                            </svg>
                            <span class="text-sm font-semibold">About</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('iot-monitor') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-blue-100 hover:bg-blue-800 {{ request()->routeIs('iot-monitor') ? 'bg-[#3B82F6] text-white hover:bg-[#3B82F6]' : '' }}">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8l-9-5-9 5 9 5 9-5z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8v8l9 5 9-5V8"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 13v8"/>
                            </svg>
                            <span class="text-sm font-semibold">IoT Box Monitor</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('dashboard') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-blue-100 hover:bg-blue-800 {{ request()->routeIs('dashboard') ? 'bg-[#3B82F6] text-white hover:bg-[#3B82F6]' : '' }}">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10zM13 21h8V11h-8v10zM13 3h8v6h-8V3zM3 21h8v-6H3v6z"/>
                            </svg>
                            <span class="text-sm font-semibold">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('donations') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-blue-100 hover:bg-blue-800 {{ request()->routeIs('donations') ? 'bg-[#3B82F6] text-white hover:bg-[#3B82F6]' : '' }}">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2 4 4 0 0 1 7 2c0 5.5-7 10-7 10z"/>
                            </svg>
                            <span class="text-sm font-semibold">Donations</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('fund-request') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-blue-100 hover:bg-blue-800 {{ request()->routeIs('fund-request') ? 'bg-[#3B82F6] text-white hover:bg-[#3B82F6]' : '' }}">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 12h10M7 17h6"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                            </svg>
                            <span class="text-sm font-semibold">Fund Request</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('reports') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-blue-100 hover:bg-blue-800 {{ request()->routeIs('reports') ? 'bg-[#3B82F6] text-white hover:bg-[#3B82F6]' : '' }}">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 19h16"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 17v-6M12 17v-9M16 17v-3"/>
                            </svg>
                            <span class="text-sm font-semibold">Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('settings') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-blue-100 hover:bg-blue-800 {{ request()->routeIs('settings') ? 'bg-[#3B82F6] text-white hover:bg-[#3B82F6]' : '' }}">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a7.9 7.9 0 0 0 .1-2l2-1.2-2-3.4-2.3.7a7.7 7.7 0 0 0-1.7-1l-.3-2.4h-4l-.3 2.4a7.7 7.7 0 0 0-1.7 1L6.9 8.4l-2 3.4 2 1.2a7.9 7.9 0 0 0 .1 2l-2 1.2 2 3.4 2.3-.7a7.7 7.7 0 0 0 1.7 1l.3 2.4h4l.3-2.4a7.7 7.7 0 0 0 1.7-1l2.3.7 2-3.4-2-1.2z"/>
                            </svg>
                            <span class="text-sm font-semibold">Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('user') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-blue-100 hover:bg-blue-800 {{ request()->routeIs('login') ? 'bg-[#3B82F6] text-white hover:bg-[#3B82F6]' : '' }}">
                            <svg class="w-5 h-5 text-white-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 1 0-16 0"/>
                            <circle cx="12" cy="8" r="3"/>
                        </svg>
                            <span class="text-sm font-semibold">{{ Auth::user()->name }}</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="p-4 border-t border-blue-700">
                <div class="text-xs text-blue-200">&copy; 2026 Gift of Hope</div>
            </div>
        </aside>

        <main class="flex-1 overflow-auto">
            @yield('content')
        </main>
    </div>
@endsection
