@extends('layouts.dashboard')

@section('title', 'Home feed - Gift of Hope')

@section('content')
@php
    $user = Auth::user();
    $memberSince = filled($user->created_at) ? \Carbon\Carbon::parse($user->created_at)->format('M Y') : '—';
    $inProgress = collect($fundRequests)->filter(fn ($request) => in_array(strtolower((string) ($request['status_name'] ?? $request['status'] ?? 'pending')), ['pending', 'under review', 'approved'], true))->count();
    $latestNotifications = collect($notifications)->take(5);
    $needsAttention = ['denied', 'funding_denied', 'liquidation_returned', 'document_resubmit'];
@endphp
<div class="hope-page">
    <div class="hope-heading">
        <div>
            <div class="hope-eyebrow">HOME FEED</div>
            <h1>Welcome back, {{ $user->fname ?: 'friend' }}.</h1>
            <p>News from the foundation and the latest on your requests.</p>
        </div>
        <a class="hope-button" href="{{ route('fund-request') }}" data-prerender>+ Create fund request</a>
    </div>

    @if (session('status'))<div class="hope-preview" role="status">{{ session('status') }}</div>@endif
    @if (session('alert_error'))<div class="hope-preview hope-preview-error" role="alert">{{ session('alert_error') }}</div>@endif

    <nav class="hope-summary" aria-label="Your account at a glance">
        <a href="{{ route('request-status') }}" data-prerender><span>Fund requests</span><strong>{{ $fundRequestCount }}</strong><small>{{ $inProgress }} in progress · View status →</small></a>
        <a href="{{ route('notifications') }}" data-prerender><span>Notifications</span><strong>{{ $notificationCount }}</strong><small>Unread updates · See all →</small></a>
        <a href="{{ route('user') }}" data-prerender><span>Member since</span><strong>{{ $memberSince }}</strong><small>My profile →</small></a>
    </nav>

    <div class="hope-feed-layout">
        <section class="hope-panel" aria-labelledby="home-feed-title">
            <header class="hope-panel-head">
                <div><h2 id="home-feed-title">Updates from Gift of Hope</h2><p>News and funding updates posted by the administrators.</p></div>
            </header>
            @include('partials.announcements', ['posts' => $posts])
        </section>

        <section class="hope-panel" aria-labelledby="home-notifications-title">
            <header class="hope-panel-head">
                <div><h2 id="home-notifications-title">Latest notifications</h2><p>Select one to mark it as read.</p></div>
                <a class="hope-text-button" href="{{ route('notifications') }}" data-prerender>See all →</a>
            </header>
            @forelse ($latestNotifications as $notification)
                <form method="POST" action="{{ route('notifications.read', $notification['id']) }}">
                    @csrf
                    <button type="submit" class="hope-notice-row {{ in_array($notification['type'] ?? '', $needsAttention, true) ? 'is-warn' : 'is-good' }}">
                        <strong>{{ $notification['label'] ?? 'Notification' }}</strong>
                        <span>{{ $notification['message'] ?? '' }}</span>
                        @if (! empty($notification['created_at']))<time datetime="{{ $notification['created_at'] }}">{{ \Carbon\Carbon::parse($notification['created_at'])->diffForHumans() }}</time>@endif
                    </button>
                </form>
            @empty
                <p class="hope-empty">You're all caught up.</p>
            @endforelse
        </section>
    </div>
</div>
@endsection
