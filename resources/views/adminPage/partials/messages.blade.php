{{-- Message thread, staff view (newest at the bottom). --}}
@forelse ($thread as $message)
    @if ($message['side'] === 'system')
        <p class="admin-thread-event">{{ $message['body'] }} · {{ $message['at'] ? \Carbon\Carbon::parse($message['at'])->format('M d, g:i A') : '' }}</p>
    @else
        <div class="admin-thread-row {{ $message['side'] === 'staff' ? 'is-staff' : '' }}">
            <div class="admin-thread-bubble">
                <strong>{{ $message['sender'] }}@if ($message['unread'] && $message['side'] === 'requester') <span class="admin-status in-progress">new</span>@endif</strong>
                <p>{{ $message['body'] }}</p>
                <small>{{ $message['at'] ? \Carbon\Carbon::parse($message['at'])->format('M d, g:i A') : '' }}</small>
            </div>
        </div>
    @endif
@empty
    <p class="admin-thread-event">No messages yet. Use this to arrange the interview or ask about documents.</p>
@endforelse
