{{--
    Transaction log entries (App\Services\TransactionLog). Read only: entries are never edited or
    deleted, and each one is chained to the one before it.
    @param array $transactions  newest first
    @param bool  $withRequest   show which request each entry is about (the full log)
    @param ?string $route       request page route name, to link the request
--}}
@php($txPeso = fn ($value) => '₱' . number_format((float) $value, 2))
@if ($transactions === [])
    <p class="tx-empty">No money has moved on {{ ($withRequest ?? false) ? 'any request' : 'this request' }} yet.</p>
@else
    <div class="tx-wrap"><table class="tx-table">
        <thead><tr><th>#</th><th>When</th><th>What</th>@if ($withRequest ?? false)<th>Request</th>@endif<th>Amount</th><th>By</th><th>Details</th></tr></thead>
        <tbody>
            @foreach ($transactions as $entry)
                <tr>
                    <td><span class="tx-seq" title="Hash {{ \Illuminate\Support\Str::limit($entry['hash'] ?? '', 16, '…') }}">{{ $entry['seq'] ?? '—' }}</span></td>
                    <td>{{ ! empty($entry['at']) ? \Carbon\Carbon::parse($entry['at'])->format('M d, Y g:i A') : '—' }}</td>
                    <td><span class="tx-type tx-{{ $entry['type'] ?? 'other' }}">{{ $entry['label'] ?? $entry['type'] ?? '—' }}</span></td>
                    @if ($withRequest ?? false)
                        <td>@if (! empty($entry['request_id']) && ($route ?? null))<a href="{{ route($route, $entry['request_id']) }}">{{ $entry['organization'] ?? 'Request' }}</a>@else{{ $entry['organization'] ?? '—' }}@endif</td>
                    @endif
                    <td>{{ isset($entry['amount']) ? $txPeso($entry['amount']) : '—' }}</td>
                    <td>{{ $entry['actor_name'] ?? '—' }}</td>
                    <td class="tx-details">@foreach ((array) ($entry['details'] ?? []) as $key => $value)@if (filled($value))<span>{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ is_numeric($value) && str_contains($key, 'funds') ? $txPeso($value) : (is_scalar($value) ? $value : json_encode($value)) }}</span>@endif @endforeach</td>
                </tr>
            @endforeach
        </tbody>
    </table></div>
@endif
