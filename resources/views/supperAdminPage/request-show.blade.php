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
    $statusLabel = ['pending' => 'In review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    $stageBadge = match (true) {
        $awaiting => 'amber',
        in_array($stage['key'], ['rejected', 'cancelled'], true) => 'red',
        in_array($stage['key'], ['completed', 'awaiting_recipients', 'to_release', 'released', 'liquidation_review', 'liquidation_returned'], true) => '',
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
<article class="sa-card sa-decision" id="decision">
    <div class="sa-card-head"><div><h2>{{ $awaiting ? 'Your final decision' : 'Decision' }}</h2><p>{{ $awaiting ? 'Check the details below, then approve or reject. The requester and the admins are notified.' : ($view['status'] === 'pending' ? 'Not ready for your decision yet: the admins are still assessing this request.' : ($view['cancelled'] ? 'Cancelled by the requester on ' . $day($view['cancelled']['at']) . '. Everything still to come was stopped, including the release of funds.' . ($view['cancelled']['reason'] ? ' Reason: “' . $view['cancelled']['reason'] . '”' : '') : 'This request has moved past final approval.')) }}</p></div></div>
    <div class="sa-card-body">
        <dl class="sa-facts">
            <div><dt>Admin recommendation</dt><dd>@if ($review){{ $review['decision'] === 'approved' ? 'Approve ' . $peso($review['amount']) : 'Reject' }}<small>{{ $review['by'] ?? 'Admin' }} · {{ $day($review['at']) }}</small>@else — @endif</dd></div>
            <div><dt>AI recommendation</dt><dd>{{ ($review['ai_amount'] ?? null) !== null ? $peso($review['ai_amount']) : '—' }}@if ($view['ai'])<small>Criticality {{ $view['ai']['score'] }}/100 · {{ $view['ai']['recommendation'] ?? 'scored' }}</small>@endif</dd></div>
            <div><dt>Amount needed</dt><dd>{{ $view['need']['amount'] !== null ? $peso($view['need']['amount']) : 'Not set' }}<small>From the social worker's assessment</small></dd></div>
            <div><dt>Checks</dt><dd>{{ ($assessment['outcome'] ?? '') === 'recommended' ? 'Social worker recommends' : (! empty($assessment['assessed_at']) ? 'Social worker does not recommend' : 'Not assessed') }}<small>{{ $verifiedDocs }} of {{ $requiredDocs->count() }} required documents verified · {{ $assessment['verified_beneficiaries'] ?? '—' }} of {{ $view['beneficiary_count'] }} people verified</small></dd></div>
            <div><dt>Free to grant</dt><dd>{{ $availableFunds !== null ? $peso($availableFunds) : 'Unavailable' }}<small>Funds on hand after every approved request; available to grant right now</small></dd></div>
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

@if ($transfer)
    @php($payoutState = $transfer['payout']['status'] ?? null)
    <article class="sa-card" id="transfer">
        <div class="sa-card-head"><div><h2>Transfer of funds</h2><p>Send the approved amount to the receiver's {{ $disbursement['mode'] === 'e_wallet' ? 'e-wallet' : 'bank account' }}.</p></div>
            <span class="sa-badge {{ match ($payoutState) { 'succeeded' => '', 'processing' => 'amber', 'failed' => 'red', default => 'gray' } }}">{{ match ($payoutState) { 'succeeded' => 'Sent', 'processing' => 'Waiting for the bank', 'failed' => 'Failed', default => $transfer['ready'] ? 'Ready to send' : 'Not ready' } }}</span></div>
        <div class="sa-card-body">
            <dl class="sa-facts" style="margin-bottom:16px">
                <div><dt>Amount</dt><dd>{{ $peso($transfer['amount']) }}</dd></div>
                <div><dt>To</dt><dd>{{ $transfer['provider'] ?? '—' }}<small>{{ $disbursement['account_name'] ?? '' }} · {{ $disbursement['account_number'] ?? '' }}</small></dd></div>
                <div><dt>Sent through</dt><dd>{{ $transfer['mode_label'] }}@if ($transfer['channel'])<small>Channel {{ $transfer['channel'] }}</small>@endif</dd></div>
                @if ($transfer['balance'] !== null)<div><dt>Payout account</dt><dd>{{ $peso($transfer['balance']) }}<small>{{ $transfer['mode'] === 'live' ? 'Real money' : 'Practice money' }}</small></dd></div>@endif
                <div><dt>Release line</dt><dd>@if ($line)#{{ $line['position'] }} · {{ $line['ready'] ? 'ready to transfer' : ($line['covered'] ? 'covered, waiting for the list' : (($line['known'] ?? true) ? $peso($line['short']) . ' more needed' : 'donation total unavailable')) }}@else — @endif<small>First come, first served</small></dd></div>
                @if (! empty($transfer['payout']['payout_id']))<div><dt>Provider reference</dt><dd>{{ $transfer['payout']['payout_id'] }}<small>{{ $transfer['payout']['provider_status'] ?? '' }}{{ ! empty($transfer['payout']['failure_code']) ? ' · ' . $transfer['payout']['failure_code'] : '' }}</small></dd></div>@endif
            </dl>
            @if ($payoutState === 'processing')
                <div class="sa-note">The transfer was sent {{ \Carbon\Carbon::parse($transfer['payout']['requested_at'])->diffForHumans() }}. The release is recorded automatically when the bank or e-wallet confirms it.</div>
                <form method="POST" action="{{ route('superadmin.payout.sync', $view['id']) }}" class="sa-inline-form" style="margin-top:10px">@csrf<button class="sa-button secondary" type="submit">Check transfer status</button></form>
            @elseif ($transfer['code_sent'] && $transfer['ready'])
                <form method="POST" action="{{ route('superadmin.payout.confirm', $view['id']) }}" class="sa-decision-form" style="max-width:420px">
                    @csrf
                    <label class="sa-field">Code from your email<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
                    <div class="sa-actions" style="justify-content:flex-start;margin-top:0"><button class="sa-button secondary" name="cancel" value="1" formnovalidate>Cancel</button><button class="sa-button">Send {{ $peso($transfer['amount']) }}</button></div>
                </form>
            @elseif ($transfer['ready'])
                <form method="POST" action="{{ route('superadmin.payout.start', $view['id']) }}" data-sa-confirm="Send {{ $peso($transfer['amount']) }} to {{ $transfer['provider'] }} ({{ $disbursement['account_name'] ?? '' }})? We will email you a code to confirm. Money that is sent cannot be taken back.">
                    @csrf
                    <button class="sa-button">Send {{ $peso($transfer['amount']) }} now</button>
                </form>
            @elseif ($payoutState !== 'succeeded')
                <div class="sa-note">@if (! $transfer['enabled'])<strong>Automatic transfers are not connected yet.</strong> Until the payout provider account is set up, an admin sends the money from the foundation's bank and records the release with the proof.@else<strong>Not ready to send:</strong>@endif
                    <ul>@foreach ($transfer['problems'] as $problem)@if ($transfer['enabled'] || ! str_starts_with($problem, 'Automatic transfers are not connected'))<li>{{ $problem }}</li>@endif @endforeach</ul></div>
            @endif
        </div>
    </article>
@endif

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
                <div><dt>Receives through</dt><dd>{{ $disbursement['provider'] ?? '—' }}<small>{{ $disbursement['mode'] === 'e_wallet' ? 'E-wallet' : 'Bank' }} · {{ $disbursement['account_name'] ?? '' }} · {{ $disbursement['account_number'] ?? '' }}</small></dd></div>
            </dl>
        </div>
    </article>
</div>

<article class="sa-card">
    <div class="sa-card-head"><div><h2>Purpose & beneficiaries</h2><p>{{ $view['beneficiary_count'] }} people asked for · {{ count($view['beneficiaries']) ?: 'names not yet' }} listed @if (isset($assessment['verified_beneficiaries'])) · {{ $assessment['verified_beneficiaries'] }} verified by the social worker @endif</p></div></div>
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

<article class="sa-card" id="amount">
    <div class="sa-card-head"><div><h2>Amount needed</h2><p>Decided by the admin / social worker from the assessment</p></div><strong>{{ $view['need']['amount'] !== null ? $peso($view['need']['amount']) : 'Not set' }}</strong></div>
    @if ($view['need']['amount'] !== null)
        <p class="sa-section-footer">{{ $view['need']['people'] }} people verified @if ($view['need']['per_person'])· about {{ $peso($view['need']['per_person']) }} per person @endif @if ($view['need']['basis'])<br>{{ $view['need']['basis'] }}@endif</p>
    @else
        <p class="sa-empty">The social worker has not recorded the amount yet.</p>
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

<article class="sa-card" id="transactions">
    <div class="sa-card-head"><div><h2>Transactions</h2><p>Every money step on this request. Entries cannot be edited or deleted.</p></div></div>
    <div class="sa-card-body">@include('partials.transactions', ['transactions' => $transactions, 'withRequest' => false])</div>
</article>

<article class="sa-card" id="messages">
    <div class="sa-card-head"><div><h2>Conversation with the requester</h2><p>Messages between the admins and the requester (read only here)</p></div><span class="sa-badge gray">{{ count($thread) }}</span></div>
    <div class="sa-card-body sa-thread">
        @forelse ($thread as $message)
            @if ($message['side'] === 'system')
                <div class="thread-system" role="note"><span aria-hidden="true">ⓘ</span><div><p>{{ $message['body'] }}</p>@if ($message['at'])<time datetime="{{ $message['at'] }}">{{ \Carbon\Carbon::parse($message['at'])->format('M d, Y · g:i A') }}</time>@endif</div></div>
            @else
                <div class="sa-thread-row is-{{ $message['side'] }}"><div><strong>{{ $message['sender'] }}</strong><p>{{ $message['body'] }}</p><small>{{ $dayTime($message['at']) }}</small></div></div>
            @endif
        @empty
            <p class="sa-muted">No messages yet.</p>
        @endforelse
    </div>
</article>
@endsection
