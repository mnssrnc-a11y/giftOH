@extends('layouts.admin')

@section('title', $view['organization'] . ' - Request - Gift of Hope')
@section('page-kicker', 'Funding request')
@section('page-title', $view['organization'])

@section('content')
@php
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
    $day = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('M d, Y') : '—';
    $dayTime = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('M d, Y g:i A') : '—';
    $stage = $view['stage'];
    $status = $view['status'];
    $assessment = $view['assessment'];
    $disbursement = $view['disbursement'];
    $liquidation = $view['liquidation'];
    $cap = $view['per_person_cap'];
    $people = $assessment['verified_beneficiaries'] ?? $view['beneficiary_count'];
    $guideline = $cap !== null && $people ? $people * $cap : null;
    $stageClass = match (true) {
        in_array($stage['key'], ['completed', 'awaiting_recipients', 'to_release', 'released'], true) => 'approved',
        in_array($stage['key'], ['rejected', 'cancelled'], true) => 'rejected',
        $stage['key'] === 'awaiting_final' => 'awaiting',
        default => 'in-progress',
    };
    $clientRows = [[
        'id' => $view['id'], 'project' => $view['organization'], 'organization' => $view['organization'],
        'amount' => $view['amount'], 'status' => $view['status'], 'date' => $view['created_at'], 'review' => [],
    ]];
    $clientData = ['people' => $people, 'routes' => [
        'action' => route('admin.fund-request.action', ['id' => '__ID__']),
        'recommendation' => route('admin.fund-request.recommendation', ['id' => '__ID__']),
    ], 'requestId' => $view['id']];
    $fileLink = fn (array $file) => $file['url']
        ? ($file['is_image']
            ? '<a href="' . e($file['url']) . '" target="_blank" rel="noopener"><img src="' . e($file['url']) . '" alt="' . e($file['label']) . '" class="admin-document-thumb" style="max-height:140px"></a>'
            : '<a class="admin-button" href="' . e($file['url']) . '" target="_blank" rel="noopener">Open ' . e(strtoupper($file['extension'] ?? 'file')) . '</a>')
        : '<span class="admin-status rejected">Missing</span>';

    // The procedure, one step at a time: what is done, what is next, and what is not open yet.
    $pending = $status === 'pending';
    $approved = in_array($status, ['approved', 'completed'], true);
    $assessed = ! empty($assessment['assessed_at']);
    $released = ! empty($disbursement['released_at']);
    $recommended = ($assessment['outcome'] ?? '') === 'recommended';
    $steps = [
        'interview' => ['Interview', ! empty($assessment['interview_at']), true],
        'documents' => ['Documents', $view['documents_verified'], true],
        'assessment' => ['Assessment', $assessed, true],
        'decision' => ['Decision', ! $pending, true],
        'release' => ['Release', $released, $approved],
        'liquidation' => ['Liquidation', $status === 'completed', $released],
    ];
    $current = match (true) {
        $errors->hasAny(['interview_at', 'social_worker', 'mode', 'location']) => 'interview',
        $errors->hasAny(['outcome', 'verified_beneficiaries', 'amount_needed', 'amount_basis', 'notes']) => 'assessment',
        $errors->hasAny(['amount', 'released_at', 'reference_no', 'proof']) => 'release',
        $errors->has('remarks') => 'liquidation',
        ! $pending && ! $approved => 'decision',              // with the super admin, rejected or cancelled
        $released => 'liquidation',
        $approved => 'release',
        empty($assessment['interview_at']) => 'interview',
        ! $view['documents_verified'] => 'documents',
        ! $assessed => 'assessment',
        default => 'decision',
    };
    $info = $errors->has('note') ? 'notes' : 'details';
    $checks = [
        ['documents', 'Every required document is verified', $view['documents_verified']],
        ['assessment', 'The social worker recommends it', $assessed && $recommended],
        ['assessment', 'The amount needed is recorded', $view['need']['amount'] !== null],
    ];
@endphp
<script>
    window.giftOfHopeFundingRequests = @json($clientRows);
    window.giftOfHopeAdminData = @json($clientData);
</script>

@if (session('status'))<div class="admin-notice" style="margin-bottom:18px">{{ session('status') }}</div>@endif
@if (session('alert_error') || $errors->any())<div class="admin-notice admin-notice-error" style="margin-bottom:18px">{{ session('alert_error') ?? $errors->first() }}</div>@endif

<div class="admin-page-head">
    <div>
        <h2>{{ $view['organization'] }}</h2>
        <p>{{ $view['category'] }} · {{ $view['beneficiary_count'] }} people · {{ $view['amount'] > 0 ? $peso($view['amount']) : 'amount not set' }} · submitted {{ $day($view['created_at']) }}</p>
        {{-- Associated with SACOP (config/sacop.php): links to the SACOP page that shows it. --}}
        @if ($associated = \App\Support\SacopAssociated::find($view['organization']))
            <a class="admin-status approved" href="{{ $associated['source'] }}" target="_blank" rel="noopener" style="margin-top:6px;text-decoration:none">✓ Associated with SACOP · {{ $associated['kind'] }} ↗</a>
        @endif
    </div>
    <div class="admin-actions">
        <span class="admin-status {{ $stageClass }}">{{ $stage['label'] }}</span>
        <a class="admin-button" href="{{ route('admin') }}#funding">← All requests</a>
    </div>
</div>

@if ($view['assessment_overdue'])
    <div class="admin-notice admin-notice-error" style="margin-bottom:18px">Assessment overdue: social workers should have assessed this request by {{ $day($view['assessment_due']) }}.</div>
@elseif ($view['liquidation_overdue'])
    <div class="admin-notice admin-notice-error" style="margin-bottom:18px">Liquidation overdue since {{ $day($view['liquidation_due']) }}. Follow up with {{ $view['contact']['Contact person'] ?? 'the requester' }}.</div>
@endif

{{-- The procedure: one step on screen at a time. --}}
<article class="admin-card proc" data-tabs="procedure" style="margin-bottom:18px">
    <div class="proc-steps" role="tablist" aria-label="Steps of this request" data-tour="procedure">
        @foreach ($steps as $key => [$label, $done, $open])
            <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="{{ $key }}" aria-selected="{{ $key === $current ? 'true' : 'false' }}"
                @class(['proc-step', 'is-done' => $done, 'is-current' => $key === $current, 'is-locked' => ! $open]) data-tab="{{ $key }}" @if (! $open) aria-disabled="true" @endif>
                <span class="proc-num" aria-hidden="true">{{ $done ? '✓' : $loop->iteration }}</span>
                <span class="proc-label">{{ $label }}<small>{{ ! $open ? 'Not yet' : ($done ? 'Done' : ($key === $current ? 'Now' : 'To do')) }}</small></span>
            </button>
        @endforeach
    </div>

    {{-- 1. Interview --}}
    <section class="proc-panel" role="tabpanel" id="interview" aria-labelledby="tab-interview" data-tab-panel="interview" @if ($current !== 'interview') hidden @endif>
        <h3>1. Interview</h3>
        <p class="admin-field-hint">The social worker interviews the requester within {{ config('funding.assessment_days') }} days (due {{ $day($view['assessment_due']) }}). The requester confirms the schedule or asks for another time.</p>
        @if (! empty($assessment['interview_at']))
            @php($answer = $view['interview_response'])
            <dl class="proc-facts">
                <div><dt>Scheduled</dt><dd>{{ $dayTime($assessment['interview_at']) }}@if (! empty($assessment['mode_label'])) · {{ $assessment['mode_label'] }}@endif @if (! empty($assessment['location'])) · {{ $assessment['location'] }}@endif</dd></div>
                <div><dt>Social worker</dt><dd>{{ $assessment['social_worker'] ?? '—' }}</dd></div>
                <div><dt>Requester's answer</dt><dd>
                    @if (! $answer) Waiting for the requester to confirm
                    @elseif ($answer['status'] === 'confirmed') <span class="admin-status approved">Confirmed</span> {{ $dayTime($answer['responded_at'] ?? null) }}
                    @else <span class="admin-status">Asked for another schedule</span> Prefers {{ $dayTime($answer['preferred_at'] ?? null) }} — “{{ $answer['note'] }}”. Reschedule below.
                    @endif
                </dd></div>
            </dl>
        @endif
        @if ($pending)
            <form method="POST" action="{{ route('admin.fund-request.interview', $view['id']) }}" class="admin-form-grid proc-form">
                @csrf
                <div class="admin-field"><label for="interview_at">Date & time</label><input id="interview_at" type="datetime-local" name="interview_at" required value="{{ old('interview_at', isset($assessment['interview_at']) ? \Carbon\Carbon::parse($assessment['interview_at'])->format('Y-m-d\TH:i') : '') }}"></div>
                <div class="admin-field"><label for="social_worker">Social worker</label><input id="social_worker" name="social_worker" placeholder="e.g. Liza Ramos, RSW" required maxlength="120" value="{{ old('social_worker', ($assessment['social_worker'] ?? '') ?: auth()->user()->fullName()) }}"></div>
                <div class="admin-field"><label for="mode">Interview type</label><select id="mode" name="mode" class="admin-select" style="width:100%">@foreach (config('funding.assessment_modes') as $value => $text)<option value="{{ $value }}" @selected(old('mode', $assessment['mode'] ?? '') === $value)>{{ $text }}</option>@endforeach</select></div>
                <div class="admin-field"><label for="location">Location / link <span style="font-weight:400">(optional)</span></label><input id="location" name="location" placeholder="e.g. Barangay hall, or meet.google.com/abc-defg-hij" maxlength="255" value="{{ old('location', $assessment['location'] ?? '') }}"></div>
                <div class="proc-actions"><button class="admin-button primary" type="submit">{{ empty($assessment['interview_at']) ? 'Schedule & notify requester' : 'Reschedule & notify' }}</button></div>
            </form>
        @endif
        @if (! empty($assessment['interview_at']))<div class="proc-next"><button type="button" class="admin-button" data-tab-go="documents">Next: documents →</button></div>@endif
    </section>

    {{-- 2. Documents --}}
    <section class="proc-panel" role="tabpanel" id="documents" aria-labelledby="tab-documents" data-tab-panel="documents" @if ($current !== 'documents') hidden @endif>
        <h3>2. Documents <span class="admin-status {{ $view['documents_verified'] ? 'verified' : 'pending-review' }}">{{ $view['documents_verified'] ? 'All verified' : 'Review needed' }}</span></h3>
        <p class="admin-field-hint">Open each document, then verify it, or ask the requester for a clearer copy.</p>
        <div class="admin-form-grid">
            @foreach ($view['documents'] as $document)
                @php($statusText = ['verified' => 'Verified', 'resubmit' => 'Resubmission requested', 'pending' => 'To review', 'missing' => 'Missing'][$document['status'] ?? ''] ?? null)
                <div class="admin-doc">
                    <div class="admin-doc-head"><label>{{ $document['label'] }}@if ($document['required']) <span style="color:#dc2626">*</span>@endif</label>@if ($statusText)<span class="admin-status {{ $document['status'] === 'pending' ? 'pending-review' : $document['status'] }}">{{ $statusText }}</span>@endif</div>
                    {!! $fileLink($document) !!}
                    @if (! empty($document['remarks']) && $document['status'] === 'resubmit')<p class="admin-field-hint">Asked: “{{ $document['remarks'] }}”</p>@endif
                    @if (! empty($document['field']) && $document['url'] && $pending && in_array($document['status'], ['pending', 'verified'], true))
                        <form method="POST" action="{{ route('admin.fund-request.document-review', [$view['id'], $document['field']]) }}">
                            @csrf
                            @if ($document['status'] !== 'verified')<button class="admin-button success" name="decision" value="verified">Verify</button>@endif
                            <input name="remarks" class="admin-input" maxlength="1000" placeholder="e.g. The letter is blurry; please upload a clearer scan.">
                            <button class="admin-button warning" name="decision" value="resubmit">Ask to resubmit</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($view['documents_verified'])<div class="proc-next"><button type="button" class="admin-button" data-tab-go="assessment">Next: assessment →</button></div>@endif
    </section>

    {{-- 3. Assessment --}}
    <section class="proc-panel" role="tabpanel" id="assessment" aria-labelledby="tab-assessment" data-tab-panel="assessment" @if ($current !== 'assessment') hidden @endif>
        <h3>3. Assessment</h3>
        @if ($assessed)
            <dl class="proc-facts">
                <div><dt>Outcome</dt><dd>{{ $recommended ? 'Recommended' : 'Not recommended' }} · {{ $assessment['verified_beneficiaries'] ?? '—' }} of {{ $view['beneficiary_count'] }} people verified · {{ $day($assessment['assessed_at']) }}</dd></div>
                <div><dt>Amount needed</dt><dd>@if ($view['need']['amount'] !== null){{ $peso($view['need']['amount']) }}@if ($view['need']['per_person']) · about {{ $peso($view['need']['per_person']) }} per person @endif @if ($view['need']['basis'])<br><small>{{ $view['need']['basis'] }}</small>@endif @else Not recorded @endif</dd></div>
                <div><dt>Assessed by</dt><dd>{{ $assessment['social_worker'] ?? '—' }}</dd></div>
                @if (! empty($assessment['notes']))<div class="is-wide"><dt>Findings</dt><dd style="white-space:pre-line">{{ $assessment['notes'] }}</dd></div>@endif
            </dl>
            @if ($pending)
                <details class="proc-change" @if ($errors->hasAny(['amount_needed', 'outcome', 'notes', 'verified_beneficiaries']) || ($recommended && $view['need']['amount'] === null)) open @endif>
                    <summary>Change the assessment or the amount needed</summary>
                    @include('adminPage.partials.assessment-form')
                </details>
                <div class="proc-next"><button type="button" class="admin-button" data-tab-go="decision">Next: decision →</button></div>
            @endif
        @elseif ($pending && empty($assessment['interview_at']))
            <p class="proc-empty">Schedule the interview first: the assessment records what the social worker found in it.</p>
            <div class="proc-next"><button type="button" class="admin-button" data-tab-go="interview">← Go to the interview</button></div>
        @elseif ($pending)
            <p class="admin-field-hint">After the interview, record the social worker's findings and the amount the request needs. Approval requires "recommended".</p>
            @include('adminPage.partials.assessment-form')
        @else
            <p class="proc-empty">No assessment was recorded.</p>
        @endif
    </section>

    {{-- 4. Decision --}}
    <section class="proc-panel" role="tabpanel" id="decision" aria-labelledby="tab-decision" data-tab-panel="decision" @if ($current !== 'decision') hidden @endif>
        <h3>4. Decision</h3>
        @if ($stage['key'] === 'cancelled')
            <p>The requester cancelled this request on {{ $day($view['cancelled']['at'] ?? null) }}@if (! empty($view['cancelled']['stage'])) (it was at: {{ $view['cancelled']['stage'] }})@endif. Everything still to come was stopped, including the release of funds.@if (! empty($view['cancelled']['reason'])) Reason: “{{ $view['cancelled']['reason'] }}”@endif</p>
        @elseif ($pending)
            <p class="admin-field-hint">Your decision is a recommendation: the super admin makes the final one.</p>
            <ul class="proc-checks">
                @foreach ($checks as [$step, $text, $ok])
                    <li class="{{ $ok ? 'is-ok' : 'is-missing' }}"><span aria-hidden="true">{{ $ok ? '✓' : '•' }}</span>{{ $text }}@unless ($ok) <button type="button" class="admin-link-button" data-tab-go="{{ $step }}">Go to {{ $step }}</button>@endunless</li>
                @endforeach
            </ul>
            @if ($view['need']['amount'] !== null)<p>Amount needed: <strong>{{ $peso($view['need']['amount']) }}</strong>@if ($guideline !== null) · guideline {{ $people }} × {{ $peso($cap) }} = {{ $peso($guideline) }}@endif. The AI recommendation also shares funds with the other {{ max(0, $pendingCount - 1) }} pending request(s).</p>@endif
            @if ($assessed)
                <div class="admin-actions">
                    <button class="admin-button danger" data-decision-open="rejected" data-request-id="{{ $view['id'] }}">Reject</button>
                    @if ($recommended && $view['need']['amount'] !== null && $view['documents_verified'])
                        <button class="admin-button success" data-decision-open="approved" data-request-id="{{ $view['id'] }}">Approve with AI recommendation</button>
                    @endif
                </div>
            @else
                <p class="proc-empty">Record the assessment first.</p>
            @endif
        @else
            <dl class="proc-facts">
                @if ($view['admin_review'])<div><dt>Your team's recommendation</dt><dd>{{ $view['admin_review']['decision'] === 'approved' ? 'Approve ' . $peso($view['admin_review']['amount']) : 'Reject' }} · {{ $view['admin_review']['by'] ?? 'admin' }}, {{ $day($view['admin_review']['at']) }}</dd></div>@endif
                <div><dt>Final decision</dt><dd>@if ($stage['key'] === 'awaiting_final') Waiting for the super admin @elseif ($approved) Approved {{ $view['granted'] !== null ? $peso($view['granted']) : '' }} @else Rejected @endif</dd></div>
                @if ($view['final_notes'])<div class="is-wide"><dt>Super admin's note</dt><dd>{{ $view['final_notes'] }}</dd></div>@endif
            </dl>
            @if ($approved)<div class="proc-next"><button type="button" class="admin-button" data-tab-go="release">Next: release →</button></div>@endif
        @endif

        <details class="proc-change" id="ai">
            <summary>AI assessment @if ($view['ai'])· {{ $view['ai']['score'] }}/100 · {{ $view['ai']['recommendation'] ?? 'scored' }}@else · not scored yet @endif</summary>
            @if ($view['ai'])
                @foreach ($view['ai']['breakdown'] as $criterion => $score)
                    <div class="admin-allocation-row" style="margin-bottom:8px"><div><span>{{ ucwords(str_replace('_', ' ', $criterion)) }}</span><b>{{ $score }}</b></div><div class="admin-progress"><span style="width:{{ $score }}%"></span></div></div>
                @endforeach
                <p>{{ $view['ai']['reasoning'] }}</p>
                @if ($view['ai']['red_flags'])<p style="margin-bottom:4px"><strong>Verify during the interview:</strong></p><ul style="margin:0;padding-left:18px;font-size:12px">@foreach ($view['ai']['red_flags'] as $flag)<li>{{ $flag }}</li>@endforeach</ul>@endif
                <p class="admin-field-hint">Scored {{ $dayTime($view['ai']['scored_at']) }} · {{ $view['ai']['provider'] ?? 'AI' }}. Advisory only; the social worker's assessment decides.</p>
            @else
                <p class="admin-field-hint">Scoring runs after submission when an AI API key is configured.</p>
            @endif
            <form method="POST" action="{{ route('admin.fund-request.ai-score', $view['id']) }}">@csrf<button class="admin-button" type="submit">{{ $view['ai'] ? '↻ Re-run' : 'Run AI assessment' }}</button></form>
        </details>
    </section>

    {{-- 5. Release --}}
    <section class="proc-panel" role="tabpanel" id="release" aria-labelledby="tab-release" data-tab-panel="release" @if ($current !== 'release') hidden @endif>
        <h3>5. Release of funds</h3>
        @if (! $approved)
            <p class="proc-empty">Opens after the super admin approves the request.</p>
        @else
            <dl class="proc-facts">
                <div><dt>Approved</dt><dd>{{ $peso($view['granted'] ?? $view['amount']) }}</dd></div>
                <div><dt>{{ $disbursement['mode'] === 'e_wallet' ? 'E-wallet' : 'Bank account' }}</dt><dd>{{ $disbursement['provider'] ?? '—' }} · {{ $disbursement['account_name'] ?? '' }} · {{ $disbursement['account_number'] ?? '' }}</dd></div>
                <div><dt>People</dt><dd>{{ count($view['beneficiaries']) ? count($view['beneficiaries']) . ' listed' : 'Not listed yet (up to ' . $view['people_limit'] . ')' }}</dd></div>
                @if ($line ?? null)<div><dt>Release line</dt><dd>#{{ $line['position'] }} · {{ $line['covered'] ? 'covered by the donations on hand' : (($line['known'] ?? true) ? $peso($line['short']) . ' more needed' : 'donation total unavailable') }}</dd></div>@endif
            </dl>
            @if ($stage['key'] === 'awaiting_recipients')
                <p>Waiting for the requester to list the people who will receive the help. You will get a notification.</p>
            @elseif ($stage['key'] === 'to_release' && ($line ?? null) && ! $line['covered'])
                <p>The donations on hand go to the requests approved before this one first (first come, first served): @if ($line['known'] ?? true)<strong>{{ $peso($line['short']) }}</strong> more is needed.@else the donation total cannot be read right now.@endif You will get a notification when it is ready to transfer.</p>
            @elseif ($stage['key'] === 'to_release')
                @php($payoutState = $transfer['payout']['status'] ?? null)
                @php($account = trim(($transfer['provider'] ?? $disbursement['provider'] ?? '') . ' · ' . ($disbursement['account_name'] ?? '') . ' · ' . ($disbursement['account_number'] ?? ''), ' ·'))
                @if ($payoutState === 'processing')
                    {{-- Sent through Xendit: waiting for the bank or e-wallet. --}}
                    <div class="proc-transfer is-processing" role="status">
                        <strong>{{ $peso($transfer['payout']['amount'] ?? $transfer['amount']) }} is on its way to {{ $account }}</strong>
                        <p>Sent through {{ $transfer['mode_label'] }} {{ \Carbon\Carbon::parse($transfer['payout']['requested_at'])->diffForHumans() }}. The release is recorded here automatically when the bank or e-wallet confirms it.</p>
                        <small>Provider reference {{ $transfer['payout']['payout_id'] ?? '—' }} · {{ $transfer['payout']['provider_status'] ?? 'PENDING' }}</small>
                        <form method="POST" action="{{ route('admin.payout.sync', $view['id']) }}">@csrf<button class="admin-button" type="submit">↻ Check transfer status</button></form>
                    </div>
                @elseif ($transfer && $transfer['code_sent'] && $transfer['ready'])
                    {{-- Step 2 of sending: the code emailed to this admin. --}}
                    <form method="POST" action="{{ route('admin.payout.confirm', $view['id']) }}" class="proc-transfer">
                        @csrf
                        <strong>Send {{ $peso($transfer['amount']) }} to {{ $account }}</strong>
                        <p>We emailed you a 6-digit code. Enter it to send the money through {{ $transfer['mode_label'] }}. Money that is sent cannot be taken back.</p>
                        <div class="admin-field"><label for="payout-code">Code from your email</label><input id="payout-code" name="code" placeholder="6-digit code, e.g. 123456" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
                        <div class="admin-actions"><button class="admin-button" name="cancel" value="1" formnovalidate>Cancel</button><button class="admin-button success" type="submit">Send {{ $peso($transfer['amount']) }}</button></div>
                    </form>
                @else
                    <p class="proc-ready">Ready: {{ $peso($view['granted'] ?? $view['amount']) }} of the donations on hand is set aside for this request. Send it through Xendit, or send it yourself and record it.</p>
                    <form method="POST" action="{{ route('admin.fund-request.disbursement', $view['id']) }}" enctype="multipart/form-data" class="admin-form-grid proc-form" id="release-form">
                        @csrf
                        <input type="hidden" name="method" value="bank_transfer">
                        {{-- Set with the quick-amount buttons only: the field cannot be clicked or typed in. --}}
                        <div class="admin-field" style="grid-column:1/-1"><label for="amount">Amount released (₱)</label><input id="amount" type="number" name="amount" placeholder="Add the amount with the buttons below" step="0.01" min="1" max="{{ $view['granted'] ?? $view['amount'] }}" required readonly tabindex="-1" data-amount-buttons value="{{ old('amount', $view['granted'] ?? $view['amount']) }}" aria-describedby="amount-hint"><p class="admin-field-hint" id="amount-hint">Starts at the approved amount, {{ $peso($view['granted'] ?? $view['amount']) }}. Change it with the buttons: Clear, then add. It cannot be typed.</p></div>

                        {{-- 1. Send it now through the payout provider (Xendit), to the requester's account. --}}
                        @if ($transfer)
                            <div class="proc-transfer" style="grid-column:1/-1">
                                <strong>Send through Xendit</strong>
                                <dl class="proc-facts">
                                    <div><dt>To</dt><dd>{{ $account }}</dd></div>
                                    <div><dt>Sent through</dt><dd>{{ $transfer['enabled'] ? $transfer['mode_label'] : 'Xendit is not connected yet' }}@if ($transfer['enabled'] && $transfer['channel']) · channel {{ $transfer['channel'] }}@endif</dd></div>
                                    @if ($transfer['balance'] !== null)<div><dt>Payout account</dt><dd>{{ $peso($transfer['balance']) }} {{ $transfer['mode'] === 'live' ? '(real money)' : '(practice money)' }}</dd></div>@endif
                                </dl>
                                @if ($payoutState === 'failed')
                                    <p class="proc-transfer-failed">The last transfer did not go through ({{ $transfer['payout']['failure_code'] ?? $transfer['payout']['provider_status'] ?? 'failed' }}). Check the account details, then try again or send it yourself.</p>
                                @endif
                                @if ($transfer['ready'])
                                    <button class="admin-button success" type="submit" formaction="{{ route('admin.payout.start', $view['id']) }}" formnovalidate data-confirm-payout data-payout-to="{{ $account }}">➤ Send through Xendit</button>
                                    <small>You confirm with a code emailed to you. The status shows here.</small>
                                @else
                                    <button class="admin-button" type="button" disabled>➤ Send through Xendit</button>
                                    <ul class="proc-transfer-problems">@foreach ($transfer['problems'] as $problem)<li>{{ $problem }}</li>@endforeach</ul>
                                @endif
                            </div>
                        @endif

                        {{-- 2. Or record a transfer made outside the system. --}}
                        <details class="proc-manual" style="grid-column:1/-1" @if (! ($transfer['enabled'] ?? false) || $errors->hasAny(['released_at', 'reference_no', 'proof', 'notes'])) open @endif>
                            <summary>{{ ($transfer['enabled'] ?? false) ? 'Sent it yourself? Record the transfer' : 'Record the transfer' }}</summary>
                            <div class="admin-form-grid">
                                <div class="admin-field"><label for="released_at">Date released</label><input id="released_at" type="date" name="released_at" required max="{{ now()->toDateString() }}" value="{{ old('released_at', now()->toDateString()) }}"></div>
                                <div class="admin-field"><label for="reference_no">Transfer reference no.</label><input id="reference_no" name="reference_no" placeholder="e.g. GCash ref. 9012345678901" maxlength="120" required value="{{ old('reference_no') }}"></div>
                                <div class="admin-field"><label for="proof">Proof (transfer slip)</label><input id="proof" type="file" name="proof" required accept=".jpg,.jpeg,.png,.pdf" style="height:auto;padding:9px"></div>
                                <div class="admin-field"><label for="release_notes">Notes <span style="font-weight:400">(optional)</span></label><input id="release_notes" name="notes" placeholder="e.g. Sent from the foundation's BDO account" maxlength="2000" value="{{ old('notes') }}"></div>
                                <div class="proc-actions" style="grid-column:1/-1"><button class="admin-button" type="submit">Record release & request liquidation</button></div>
                            </div>
                        </details>
                    </form>
                @endif
            @else
                <dl class="proc-facts">
                    <div><dt>Released</dt><dd>{{ $peso($disbursement['amount']) }} · {{ $day($disbursement['released_at']) }} · ref. {{ $disbursement['reference_no'] ?? '—' }} · by {{ $disbursement['recorded_by'] ?? 'admin' }}</dd></div>
                    @if (($transfer['payout']['status'] ?? null) === 'succeeded')<div><dt>Sent through</dt><dd>{{ app(\App\Services\Payouts\PayoutService::class)->modeLabel($transfer['payout']['mode'] ?? 'manual') }} · {{ \Carbon\Carbon::parse($transfer['payout']['completed_at'] ?? $transfer['payout']['requested_at'])->format('M d, Y g:i A') }}</dd></div>@endif
                    @if ($disbursement['proof'])<div><dt>Proof</dt><dd><a class="admin-button" href="{{ $disbursement['proof']['url'] }}" target="_blank" rel="noopener">Open proof</a></dd></div>@endif
                </dl>
                <div class="proc-next"><button type="button" class="admin-button" data-tab-go="liquidation">Next: liquidation →</button></div>
            @endif
            {{-- The money record stays on this step: every send, failure and release for this request. --}}
            @if (($transactions ?? []) !== [])
                <details class="proc-change" @if (in_array($transfer['payout']['status'] ?? null, ['processing', 'failed'], true)) open @endif>
                    <summary>Transactions for this request ({{ count($transactions) }})</summary>
                    @include('partials.transactions', ['transactions' => $transactions, 'withRequest' => false])
                </details>
            @endif
            @if ($view['beneficiaries'])
                <details class="proc-change"><summary>People who will receive the help ({{ count($view['beneficiaries']) }})</summary><ol style="columns:2;font-size:12px;padding-left:20px">@foreach ($view['beneficiaries'] as $name)<li>{{ $name }}</li>@endforeach</ol></details>
            @endif
        @endif
    </section>

    {{-- 6. Liquidation --}}
    <section class="proc-panel" role="tabpanel" id="liquidation" aria-labelledby="tab-liquidation" data-tab-panel="liquidation" @if ($current !== 'liquidation') hidden @endif>
        <h3>6. Liquidation</h3>
        @if (! $released)
            <p class="proc-empty">Opens after the funds are released.</p>
        @elseif (! $liquidation)
            <p>Waiting for the requester's receipts and list of recipients, due {{ $day($view['liquidation_due']) }}.</p>
        @else
            <p>Submitted {{ $day($liquidation['submitted_at'] ?? null) }} · spent <strong>{{ $peso($liquidation['amount_spent'] ?? 0) }}</strong> of {{ $peso($disbursement['amount']) }} released @if (! empty($liquidation['notes']))· {{ $liquidation['notes'] }}@endif</p>
            <div class="admin-form-grid">
                @foreach ($liquidation['receipt_files'] as $i => $file)<div class="admin-field"><label>Receipt {{ $i + 1 }}</label>{!! $fileLink($file) !!}</div>@endforeach
                @if ($liquidation['recipient_file'])<div class="admin-field"><label>List of recipients</label>{!! $fileLink($liquidation['recipient_file']) !!}</div>@endif
                @foreach ($liquidation['photo_files'] as $i => $file)<div class="admin-field"><label>Photo {{ $i + 1 }}</label>{!! $fileLink($file) !!}</div>@endforeach
            </div>
            @if (! empty($liquidation['recipient_names']))
                <details class="proc-change"><summary>Recipients named ({{ count($liquidation['recipient_names']) }})</summary><ol style="columns:2;font-size:12px;padding-left:20px">@foreach ($liquidation['recipient_names'] as $name)<li>{{ $name }}</li>@endforeach</ol></details>
            @endif
            @if ($stage['key'] === 'liquidation_review')
                <form method="POST" action="{{ route('admin.fund-request.liquidation', $view['id']) }}" class="admin-post-form proc-form">
                    @csrf
                    <div class="admin-field"><label for="remarks">Remarks <span style="font-weight:400">(required when returning)</span></label><textarea id="remarks" name="remarks" placeholder="e.g. Please add the receipt for the hygiene kits (₱1,200)." class="admin-textarea" style="min-height:70px" maxlength="2000">{{ old('remarks') }}</textarea></div>
                    <div class="admin-actions"><button class="admin-button warning" name="decision" value="returned">Return for corrections</button><button class="admin-button success" name="decision" value="verified">Verify & complete</button></div>
                </form>
            @elseif ($stage['key'] === 'liquidation_returned')
                <p>Returned to the requester: “{{ $liquidation['review_remarks'] }}”. Waiting for a corrected liquidation.</p>
            @elseif ($stage['key'] === 'completed')
                <p class="proc-ready">Completed. The liquidation was verified on {{ $day($liquidation['reviewed_at'] ?? null) }}.</p>
            @endif
        @endif
    </section>
</article>

{{-- About the request, one tab at a time. --}}
<article class="admin-card proc" data-tabs="info" data-tour="info-tabs">
    <div class="proc-tabs" role="tablist" aria-label="About this request">
        @foreach (['details' => 'Request', 'notes' => 'Verification notes (' . count($view['staff_notes']) . ')', 'messages' => 'Conversation', 'transactions' => 'Transactions', 'history' => 'History'] as $key => $label)
            <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="{{ $key }}" aria-selected="{{ $key === $info ? 'true' : 'false' }}" class="proc-tab" data-tab="{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>

    <section class="proc-panel" role="tabpanel" id="details" aria-labelledby="tab-details" data-tab-panel="details" @if ($info !== 'details') hidden @endif>
        <p style="margin-top:0;white-space:pre-line">{{ $view['purpose'] }}</p>
        <dl class="proc-facts">
            <div><dt>People asked for</dt><dd>{{ $view['beneficiary_count'] }}</dd></div>
            @foreach ($view['contact'] as $label => $value)@if ($value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif @endforeach
            <div><dt>Receive funds by</dt><dd>{{ $disbursement['provider'] ?? '—' }} · {{ $disbursement['account_name'] ?? '' }}</dd></div>
        </dl>
    </section>

    <section class="proc-panel" role="tabpanel" id="notes" aria-labelledby="tab-notes" data-tab-panel="notes" @if ($info !== 'notes') hidden @endif>
        <p class="admin-field-hint" style="margin-top:0">For the foundation only: the super admin sees these when finalizing; the requester never does.</p>
        @forelse ($view['staff_notes'] as $note)
            <div class="admin-timeline-row"><strong>{{ $note['author_name'] ?? 'Admin' }} · {{ $dayTime($note['at'] ?? null) }}</strong><span style="white-space:pre-line">{{ $note['body'] }}</span></div>
        @empty
            <p>No notes yet. Record what you verified, for example a document you confirmed with the barangay.</p>
        @endforelse
        <form method="POST" action="{{ route('admin.fund-request.notes', $view['id']) }}" class="admin-post-form" style="margin-top:14px">
            @csrf
            <label for="staff-note" class="sr-only">Verification note</label>
            <textarea id="staff-note" name="note" class="admin-textarea" style="min-height:80px" maxlength="2000" required placeholder="Add a verification note…">{{ old('note') }}</textarea>
            <div class="admin-actions"><button class="admin-button primary" type="submit">Add note</button></div>
        </form>
    </section>

    <section class="proc-panel" role="tabpanel" id="messages" aria-labelledby="tab-messages" data-tab-panel="messages" data-thread="{{ route('fund-request.messages', $view['id']) }}" @if ($info !== 'messages') hidden @endif>
        <p class="admin-field-hint" style="margin-top:0">Arrange the interview, ask about the assessment, or explain a document. {{ $view['contact']['Contact person'] ?? 'The requester' }} is notified of each message.</p>
        <div class="admin-thread" data-thread-list>@include('adminPage.partials.messages', ['thread' => $thread, 'side' => 'staff'])</div>
        <form class="admin-thread-form" method="POST" action="{{ route('fund-request.messages.store', $view['id']) }}" data-thread-form>
            @csrf
            <textarea name="body" class="admin-textarea" maxlength="2000" required placeholder="Write a message… (Ctrl+Enter to send)"></textarea>
            <button class="admin-button primary" type="submit">Send</button>
        </form>
    </section>

    <section class="proc-panel" role="tabpanel" id="transactions" aria-labelledby="tab-transactions" data-tab-panel="transactions" hidden>
        <p class="admin-field-hint" style="margin-top:0">Every money step on this request. Entries cannot be edited or deleted.</p>
        @include('partials.transactions', ['transactions' => $transactions, 'withRequest' => false])
    </section>

    <section class="proc-panel" role="tabpanel" id="history" aria-labelledby="tab-history" data-tab-panel="history" hidden>
        <div class="admin-history" style="margin-top:0">
            @foreach ($view['timeline'] as $event)
                <div class="admin-timeline-row"><strong>{{ $event['label'] }}</strong><span>{{ $dayTime($event['at']) }}@if ($event['detail']) · {{ $event['detail'] }}@endif</span></div>
            @endforeach
        </div>
    </section>
</article>
@endsection
