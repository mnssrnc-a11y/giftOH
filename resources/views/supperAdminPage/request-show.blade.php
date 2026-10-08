@extends('layouts.spAdmin')
@section('title', $view['organization'] . ' - Final check - Gift of Hope')
@section('page-title', 'Fund request')
@section('sa-section', 'requests')
@section('content')
@php
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
    $day = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('M d, Y') : '—';
    $dayTime = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('M d, Y g:i A') : '—';
    $stage = $view['stage'];
    $assessment = $view['assessment'];
    $review = $view['admin_review'];
    $disbursement = $view['disbursement'];
    $liquidation = $view['liquidation'];
    $awaiting = $stage['key'] === 'awaiting_final';
    $people = $assessment['verified_beneficiaries'] ?? $view['beneficiary_count'];
    $requiredDocs = collect($view['documents'])->where('required', true);
    $verifiedDocs = $requiredDocs->where('status', 'verified')->count();
    $statusLabel = ['pending' => 'In review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'completed' => 'Completed'];
    $stageBadge = match (true) {
        $awaiting => 'amber',
        $stage['key'] === 'rejected' => 'red',
        in_array($stage['key'], ['completed', 'to_release', 'released', 'liquidation_review', 'liquidation_returned'], true) => '',
        default => 'gray',
    };
    $fileLink = fn (array $file) => $file['url']
        ? ($file['is_image']
            ? '<a href="' . e($file['url']) . '" target="_blank" rel="noopener"><img src="' . e($file['url']) . '" alt="' . e($file['label']) . '" class="sa-doc-thumb" loading="lazy"></a>'
            : '<a class="sa-button secondary" href="' . e($file['url']) . '" target="_blank" rel="noopener">Open ' . e(strtoupper($file['extension'] ?? 'file')) . '</a>')
        : '<span class="sa-badge red">Missing</span>';
    $docStatus = ['verified' => ['Verified', ''], 'resubmit' => ['Resubmission asked', 'red'], 'pending' => ['Not yet verified', 'amber'], 'missing' => ['Missing', 'red']];
@endphp


<div class="sa-heading">
    <div>
        <a class="sa-link" href="{{ route('superadmin') }}#requests">← All fund requests</a>
        <span class="sa-eyebrow" style="display:block;margin-top:12px">FINAL CHECK</span>
        <h1>{{ $view['organization'] }}</h1>
        <p>{{ $view['category'] }} · {{ $view['beneficiary_count'] }} people listed · submitted {{ $day($view['created_at']) }} · request {{ \Illuminate\Support\Str::limit($view['id'], 8, '') }}</p>
    </div>
    <span class="sa-badge {{ $stageBadge }}">{{ $stage['label'] }}</span>
</div>

{{-- Decision summary: what the admin recommends and what to check before finalizing. --}}
<article class="sa-card sa-decision">
    <div class="sa-card-head"><div><h2>{{ $awaiting ? 'Your final decision' : 'Decision' }}</h2><p>{{ $awaiting ? 'Check the details below, then approve or reject. The requester and the admins are notified.' : 'This request has moved past final approval.' }}</p></div></div>
    <div class="sa-card-body">
        <dl class="sa-facts">
            <div><dt>Admin recommendation</dt><dd>@if ($review){{ $review['decision'] === 'approved' ? 'Approve ' . $peso($review['amount']) : 'Reject' }}<small>{{ $review['by'] ?? 'Admin' }} · {{ $day($review['at']) }}</small>@else — @endif</dd></div>
            <div><dt>AI recommendation</dt><dd>{{ ($review['ai_amount'] ?? null) !== null ? $peso($review['ai_amount']) : '—' }}@if ($view['ai'])<small>Criticality {{ $view['ai']['score'] }}/100 · {{ $view['ai']['recommendation'] ?? 'scored' }}</small>@endif</dd></div>
            <div><dt>Budget</dt><dd>{{ $view['budget']['per_person'] ? $peso($view['budget']['per_person']) . ' × ' . ($view['budget']['people'] ?? $people) . ' people' : 'Not set' }}<small>Total {{ $peso($view['amount']) }}</small></dd></div>
            <div><dt>Checks</dt><dd>{{ ($assessment['outcome'] ?? '') === 'recommended' ? 'Social worker recommends' : (! empty($assessment['assessed_at']) ? 'Social worker does not recommend' : 'Not assessed') }}<small>{{ $verifiedDocs }} of {{ $requiredDocs->count() }} required documents verified · {{ $assessment['verified_beneficiaries'] ?? '—' }} of {{ $view['beneficiary_count'] }} people verified</small></dd></div>
            <div><dt>Unallocated funds</dt><dd>{{ $availableFunds !== null ? $peso($availableFunds) : 'Unavailable' }}<small>Available to grant right now</small></dd></div>
            @if ($view['granted'] !== null)<div><dt>Approved amount</dt><dd>{{ $peso($view['granted']) }}</dd></div>@endif
        </dl>
        @if (! empty($review['notes']))<div class="sa-note">Admin's note: “{{ $review['notes'] }}”</div>@endif
        @if ($view['final_notes'])<div class="sa-note">Your note: “{{ $view['final_notes'] }}”</div>@endif

        @if ($awaiting)
            <div class="sa-decision-forms">
                <form method="POST" action="{{ route('superadmin.fund-request.finalize', $view['id']) }}" class="sa-decision-form" data-sa-confirm="Approve this request? This is the final decision and the requester is notified immediately.">
                    @csrf
                    <input type="hidden" name="decision" value="approved"><input type="hidden" name="from" value="detail">
                    <label class="sa-field">Amount to grant (₱)<input type="number" name="amount" min="1" max="{{ $view['amount'] }}" step="0.01" required value="{{ old('amount', $review['amount'] ?? $view['amount']) }}"><small>Prefilled with the admin's recommendation; at most {{ $peso($view['amount']) }}.</small></label>
                    <label class="sa-field">Note (optional)<textarea name="notes" maxlength="1000">{{ old('notes') }}</textarea></label>
                    <button class="sa-button" type="submit">Approve request</button>
                </form>
                <form method="POST" action="{{ route('superadmin.fund-request.finalize', $view['id']) }}" class="sa-decision-form" data-sa-confirm="Reject this request? This is the final decision and the requester is notified immediately.">
                    @csrf
                    <input type="hidden" name="decision" value="rejected"><input type="hidden" name="from" value="detail">
                    <label class="sa-field">Reason shown to the requester<textarea name="notes" maxlength="1000" required placeholder="Why the request is rejected"></textarea></label>
                    <button class="sa-button danger" type="submit">Reject request</button>
                </form>
            </div>
        @endif
    </div>
</article>

<div class="sa-grid equal">
    <article class="sa-card">
        <div class="sa-card-head"><div><h2>Requester account</h2><p>Who submitted this request</p></div></div>
        <div class="sa-card-body">
            @if ($requester)
                <dl class="sa-facts">
                    <div><dt>Name</dt><dd>{{ trim(($requester['fname'] ?? '') . ' ' . ($requester['mname'] ?? '') . ' ' . ($requester['lname'] ?? '')) ?: '—' }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $requester['email'] ?? '—' }}</dd></div>
                    <div><dt>Phone</dt><dd>{{ $requester['phone'] ?? '—' }}</dd></div>
                    <div><dt>Address</dt><dd>{{ $requester['address'] ?? '—' }}</dd></div>
                    <div><dt>Account since</dt><dd>{{ $day($requester['created_at'] ?? null) }}</dd></div>
                    <div><dt>Access</dt><dd>{{ filter_var($requester['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Disabled' }}</dd></div>
                </dl>
                <h3 class="sa-subhead">Earlier requests from this account</h3>
                @forelse ($earlier as $other)
                    <div class="sa-device"><div><strong>{{ $other['organization'] }}</strong><small>{{ $day($other['created_at']) }}{{ $other['granted'] ? ' · ' . $peso($other['granted']) . ' granted' : '' }}</small></div><a class="sa-link" href="{{ route('superadmin.fund-request.show', $other['id']) }}">{{ $statusLabel[$other['status']] ?? ucfirst($other['status']) }} ↗</a></div>
                @empty
                    <p class="sa-muted">This is the account's first request.</p>
                @endforelse
            @else
                <p class="sa-muted">The account that submitted this request no longer exists.</p>
            @endif
        </div>
    </article>

    <article class="sa-card">
        <div class="sa-card-head"><div><h2>Organization & contact</h2><p>As entered on the request</p></div></div>
        <div class="sa-card-body">
            <dl class="sa-facts">
                @foreach ($view['contact'] as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>@endforeach
                <div><dt>Receiving bank</dt><dd>{{ $disbursement['bank_name'] ?? '—' }}<small>{{ $disbursement['account_name'] ?? '' }} · {{ $disbursement['account_number'] ?? '' }}</small></dd></div>
            </dl>
        </div>
    </article>
</div>

<article class="sa-card">
    <div class="sa-card-head"><div><h2>Purpose & beneficiaries</h2><p>{{ count($view['beneficiaries']) }} listed @if (isset($assessment['verified_beneficiaries'])) · {{ $assessment['verified_beneficiaries'] }} verified by the social worker @endif</p></div></div>
    <div class="sa-card-body">
        <p class="sa-prose">{{ $view['purpose'] ?: '—' }}</p>
        @if ($view['beneficiaries'])
            <details class="sa-details"><summary>Show the {{ count($view['beneficiaries']) }} people listed</summary><ol class="sa-columns">@foreach ($view['beneficiaries'] as $name)<li>{{ $name }}</li>@endforeach</ol></details>
        @endif
    </div>
</article>

<article class="sa-card" id="documents">
    <div class="sa-card-head"><div><h2>Documents</h2><p>Verified by the admins before they recommend a decision</p></div><span class="sa-badge {{ $view['documents_verified'] ? '' : 'amber' }}">{{ $view['documents_verified'] ? 'All verified' : 'Review incomplete' }}</span></div>
    <div class="sa-card-body sa-doc-grid">
        @forelse ($view['documents'] as $document)
            @php([$docLabel, $docClass] = $docStatus[$document['status'] ?? ''] ?? [null, 'gray'])
            <div class="sa-doc">
                <div class="sa-doc-head"><strong>{{ $document['label'] }}</strong>@if ($docLabel)<span class="sa-badge {{ $docClass }}">{{ $docLabel }}</span>@endif</div>
                {!! $fileLink($document) !!}
                @if (! empty($document['remarks']))<small>Admin remarks: “{{ $document['remarks'] }}”</small>@endif
            </div>
        @empty
            <p class="sa-muted">No documents were attached.</p>
        @endforelse
    </div>
</article>

<div class="sa-grid equal">
    <article class="sa-card">
        <div class="sa-card-head"><div><h2>Social worker assessment</h2><p>Interview and findings</p></div></div>
        <div class="sa-card-body">
            <dl class="sa-facts">
                <div><dt>Interview</dt><dd>{{ $dayTime($assessment['interview_at'] ?? null) }}<small>{{ $assessment['mode_label'] ?? '' }}{{ ! empty($assessment['location']) ? ' · ' . $assessment['location'] : '' }}</small></dd></div>
                <div><dt>Social worker</dt><dd>{{ $assessment['social_worker'] ?? '—' }}</dd></div>
                <div><dt>Outcome</dt><dd>@if (! empty($assessment['assessed_at'])){{ ($assessment['outcome'] ?? '') === 'recommended' ? 'Recommended' : 'Not recommended' }}<small>{{ $day($assessment['assessed_at']) }} · recorded by {{ $assessment['assessed_by_name'] ?? 'an admin' }}</small>@else Not yet assessed @endif</dd></div>
                <div><dt>Requester's answer</dt><dd>{{ match ($view['interview_response']['status'] ?? null) { 'confirmed' => 'Confirmed the interview', 'reschedule_requested' => 'Asked for another schedule', default => '—' } }}</dd></div>
            </dl>
            @if (! empty($assessment['notes']))<p class="sa-prose"><strong>Findings:</strong> {{ $assessment['notes'] }}</p>@endif
        </div>
    </article>

    <article class="sa-card">
        <div class="sa-card-head"><div><h2>AI assessment</h2><p>{{ $view['ai']['provider'] ?? 'Advisory only' }}</p></div>@if ($view['ai'])<strong class="sa-score-big">{{ $view['ai']['score'] }}<small>/100</small></strong>@endif</div>
        <div class="sa-card-body">
            @if ($view['ai'])
                @foreach ($view['ai']['breakdown'] as $criterion => $score)
                    <div class="sa-score"><div><span>{{ ucwords(str_replace('_', ' ', $criterion)) }}</span><strong>{{ $score }}</strong></div><progress value="{{ $score }}" max="100" aria-label="{{ $criterion }}"></progress></div>
                @endforeach
                @if ($view['ai']['reasoning'])<p class="sa-prose">{{ $view['ai']['reasoning'] }}</p>@endif
                @if ($view['ai']['red_flags'])<div class="sa-note sa-note-error"><strong>Points to verify:</strong><ul>@foreach ($view['ai']['red_flags'] as $flag)<li>{{ $flag }}</li>@endforeach</ul></div>@endif
            @else
                <p class="sa-muted">Not scored. AI scoring runs when an AI API key is configured.</p>
            @endif
        </div>
    </article>
</div>

<article class="sa-card" id="budget">
    <div class="sa-card-head"><div><h2>Budget per person</h2><p>Items and quantities chosen by the admin, priced from your price list</p></div><strong>{{ $view['budget']['per_person'] ? $peso($view['budget']['per_person']) . ' / person' : 'Not set' }}</strong></div>
    @if ($view['budget']['items'])
        <div class="sa-table-wrap"><table class="sa-table"><thead><tr><th>Item</th><th>Qty / person</th><th>Unit price</th><th>Price check</th><th>Subtotal</th></tr></thead><tbody>
            @foreach ($view['budget']['items'] as $item)
                <tr><td><strong>{{ $item['name'] }}</strong></td><td>{{ $item['qty'] }}</td><td>{{ $peso($item['unit_price']) }}</td>
                    <td>@if ($item['check'])<span class="sa-badge {{ $item['check'] === 'above' ? 'red' : ($item['check'] === 'within' ? '' : 'gray') }}">{{ $item['check'] }}</span><small>{{ $peso($item['reference_min']) }}–{{ $peso($item['reference_max']) }}</small>@else<small>—</small>@endif</td>
                    <td>{{ $peso($item['subtotal']) }}</td></tr>
            @endforeach
        </tbody></table></div>
        <p class="sa-section-footer">{{ $view['budget']['people'] ?? $people }} people × {{ $peso($view['budget']['per_person']) }} = <strong>{{ $peso($view['amount']) }}</strong>@if ($view['per_person_cap']) · foundation guideline about {{ $peso($view['per_person_cap']) }} per person @endif</p>
    @else
        <p class="sa-empty">The admin has not set a budget yet.</p>
    @endif
</article>

<div class="sa-grid equal">
    <article class="sa-card" id="notes">
        <div class="sa-card-head"><div><h2>Verification notes</h2><p>Written by the admins for you; the requester never sees them</p></div><span class="sa-badge gray">{{ count($view['staff_notes']) }}</span></div>
        <div class="sa-card-body">
            @forelse ($view['staff_notes'] as $note)
                <div class="sa-event"><span class="sa-event-icon" aria-hidden="true">✎</span><div><strong style="white-space:pre-line">{{ $note['body'] }}</strong><small>{{ $note['author_name'] ?? 'Admin' }} · {{ $dayTime($note['at'] ?? null) }}</small></div></div>
            @empty
                <p class="sa-muted">No verification notes.</p>
            @endforelse
        </div>
    </article>

    <article class="sa-card">
        <div class="sa-card-head"><div><h2>History</h2><p>Every step on this request</p></div></div>
        <div class="sa-card-body">
            @foreach ($view['timeline'] as $event)
                <div class="sa-event"><span class="sa-event-icon" aria-hidden="true">•</span><div><strong>{{ $event['label'] }}</strong><small>{{ $dayTime($event['at']) }}@if ($event['detail']) · {{ $event['detail'] }}@endif</small></div></div>
            @endforeach
        </div>
    </article>
</div>

@if ($disbursement['released_at'] || $liquidation)
<article class="sa-card">
    <div class="sa-card-head"><div><h2>Release & liquidation</h2><p>After approval</p></div></div>
    <div class="sa-card-body">
        @if ($disbursement['released_at'])<p class="sa-prose">Released {{ $peso($disbursement['amount']) }} on {{ $day($disbursement['released_at']) }} · ref. {{ $disbursement['reference_no'] ?? '—' }} · by {{ $disbursement['recorded_by'] ?? 'an admin' }} @if ($disbursement['proof'])· <a class="sa-link" href="{{ $disbursement['proof']['url'] }}" target="_blank" rel="noopener">Proof of release ↗</a>@endif</p>@endif
        @if ($liquidation)
            <p class="sa-prose">Liquidation {{ $liquidation['status'] ?? '' }} · ₱{{ number_format((float) ($liquidation['amount_spent'] ?? 0), 2) }} spent · {{ count($liquidation['receipt_files']) }} receipt file(s)</p>
            <div class="sa-doc-grid">
                @foreach ($liquidation['receipt_files'] as $i => $file)<div class="sa-doc"><div class="sa-doc-head"><strong>Receipt {{ $i + 1 }}</strong></div>{!! $fileLink($file) !!}</div>@endforeach
                @if ($liquidation['recipient_file'])<div class="sa-doc"><div class="sa-doc-head"><strong>List of recipients</strong></div>{!! $fileLink($liquidation['recipient_file']) !!}</div>@endif
            </div>
        @endif
    </div>
</article>
@endif

<article class="sa-card" id="messages">
    <div class="sa-card-head"><div><h2>Conversation with the requester</h2><p>Messages between the admins and the requester (read only here)</p></div><span class="sa-badge gray">{{ count($thread) }}</span></div>
    <div class="sa-card-body sa-thread">
        @forelse ($thread as $message)
            <div class="sa-thread-row is-{{ $message['side'] }}"><div><strong>{{ $message['sender'] }}</strong><p>{{ $message['body'] }}</p><small>{{ $dayTime($message['at']) }}</small></div></div>
        @empty
            <p class="sa-muted">No messages yet.</p>
        @endforelse
    </div>
</article>
@endsection
