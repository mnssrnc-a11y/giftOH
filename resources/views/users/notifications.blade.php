@extends('layouts.dashboard')

@section('title', 'Notifications - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50 p-8">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
                    <p class="text-sm text-gray-500">Your latest fund request updates.</p>
                </div>
                <a href="{{ route('dashboarduser') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Back to dashboard
                </a>
            </div>

            <div class="space-y-3">
                @forelse ($notifications as $notification)
                    @php($isApproved = in_array($notification['type'] ?? '', ['approved', 'funding_approved'], true))
                    <form method="POST" action="{{ route('notifications.read', $notification['id']) }}">
                        @csrf
                        <button type="submit" class="flex w-full items-start gap-4 rounded-lg border-l-4 p-4 text-left {{ $isApproved ? 'bg-green-50 border-green-500' : 'bg-red-50 border-red-500' }} hover:shadow-md focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900">{{ $notification['label'] ?? 'Notification' }}</p>
                                <p class="text-sm text-gray-600">{{ $notification['message'] ?? '' }}</p>
                                @if (!empty($notification['created_at']))
                                    <p class="mt-1 text-xs text-gray-500">{{ \Carbon\Carbon::parse($notification['created_at'])->diffForHumans() }}</p>
                                @endif
                            </div>
                        </button>
                    </form>
                @empty
                    <div class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow-sm">
                        No notifications yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
