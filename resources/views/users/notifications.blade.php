@extends(\App\Support\Layout::forRole())

@section('title', 'Notifications - Gift of Hope')
@section('page-kicker', 'Account')
@section('page-title', 'Notifications')

@section('content')
@php($warnTypes = ['denied', 'funding_denied', 'liquidation_returned', 'document_resubmit', 'price_update_failed'])
<div class="hope-page">
    <div class="hope-heading">
        <div>
            <div class="hope-eyebrow">NOTIFICATIONS</div>
            <h1>What needs your attention</h1>
            <p>{{ auth()->user()->isUser() ? 'Updates on your fund requests.' : 'New requests, replies, decisions and system updates. Open one to go straight to it.' }}</p>
        </div>
        @if ($notifications)
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="hope-button secondary" type="submit">Mark all as read</button></form>
        @endif
    </div>

    @if (session('status'))<div class="hope-preview" role="status">{{ session('status') }}</div>@endif
    @if (session('alert_error'))<div class="hope-preview hope-preview-error" role="alert">{{ session('alert_error') }}</div>@endif

    <section class="hope-panel">
        @forelse ($notifications as $notification)
            <form method="POST" action="{{ route('notifications.read', $notification['id']) }}">
                @csrf
                <button type="submit" class="hope-notice-row {{ in_array($notification['type'] ?? '', $warnTypes, true) || str_contains(strtolower($notification['label'] ?? ''), 'fail') ? 'is-warn' : '' }}">
                    <strong>{{ $notification['label'] ?? 'Notification' }}</strong>
                    <span>{{ $notification['message'] ?? '' }}</span>
                    @if (! empty($notification['created_at']))<time datetime="{{ $notification['created_at'] }}">{{ \Carbon\Carbon::parse($notification['created_at'])->diffForHumans() }}</time>@endif
                </button>
            </form>
        @empty
            <p class="hope-empty">You're all caught up. New notifications appear here.</p>
        @endforelse
    </section>
</div>
@endsection
