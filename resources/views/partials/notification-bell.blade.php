{{-- Notification bell for every workspace topbar: unread count, the latest few, and a link to all. --}}
@php($bellCount = count($navNotifications ?? []))
<div class="notify" data-notify>
    <button type="button" class="notify-button" data-notify-toggle aria-haspopup="true" aria-expanded="false" aria-controls="notify-panel"
        aria-label="Notifications{{ $bellCount ? " ({$bellCount} unread)" : '' }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        @if ($bellCount)<b>{{ $bellCount > 99 ? '99+' : $bellCount }}</b>@endif
    </button>
    <div class="notify-panel" id="notify-panel" data-notify-panel hidden>
        <div class="notify-head">
            <strong>Notifications</strong>
            @if ($bellCount)<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button type="submit">Mark all read</button></form>@endif
        </div>
        <div class="notify-list">
            @forelse (array_slice($navNotifications ?? [], 0, 6) as $item)
                <form method="POST" action="{{ route('notifications.read', $item['id']) }}">
                    @csrf
                    <button type="submit" class="notify-item">
                        <strong>{{ $item['label'] ?? 'Notification' }}</strong>
                        <span>{{ \Illuminate\Support\Str::limit($item['message'] ?? '', 110) }}</span>
                        @if (! empty($item['created_at']))<time datetime="{{ $item['created_at'] }}">{{ \Carbon\Carbon::parse($item['created_at'])->diffForHumans() }}</time>@endif
                    </button>
                </form>
            @empty
                <p class="notify-empty">You're all caught up.</p>
            @endforelse
        </div>
        <a class="notify-all" href="{{ route('notifications') }}">See all notifications</a>
    </div>
</div>
