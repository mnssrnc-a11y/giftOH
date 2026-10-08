{{-- Message thread, requester's view (newest at the bottom). --}}
@forelse ($thread as $message)
    @if ($message['side'] === 'system')
        <p class="my-3 text-center text-xs text-gray-500">{{ $message['body'] }} · {{ $message['at'] ? \Carbon\Carbon::parse($message['at'])->format('M d, g:i A') : '' }}</p>
    @else
        @php($mine = $message['side'] === 'requester')
        <div class="my-2 flex {{ $mine ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-[80%] rounded-2xl px-4 py-2 text-sm {{ $mine ? 'bg-[#0D47A1] text-white' : 'bg-gray-100 text-gray-900' }}">
                @unless ($mine)<p class="mb-0.5 text-xs font-semibold text-gray-500">{{ $message['sender'] }}</p>@endunless
                <p class="whitespace-pre-line break-words">{{ $message['body'] }}</p>
                <p class="mt-1 text-right text-[10px] {{ $mine ? 'text-blue-200' : 'text-gray-400' }}">{{ $message['at'] ? \Carbon\Carbon::parse($message['at'])->format('M d, g:i A') : '' }}</p>
            </div>
        </div>
    @endif
@empty
    <p class="py-6 text-center text-sm text-gray-500">No messages yet. Ask the foundation about your interview, assessment or documents here.</p>
@endforelse
