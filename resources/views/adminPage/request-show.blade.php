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
    $assessment = $view['assessment'];
    $disbursement = $view['disbursement'];
    $liquidation = $view['liquidation'];
    $cap = $view['per_person_cap'];
    $people = $assessment['verified_beneficiaries'] ?? $view['beneficiary_count'];
    $guideline = $cap !== null && $people ? $people * $cap : null;
    $stageClass = match (true) {
        in_array($stage['key'], ['completed', 'to_release', 'released'], true) => 'approved',
        $stage['key'] === 'rejected' => 'rejected',
        $stage['key'] === 'awaiting_final' => 'awaiting',
        default => 'in-progress',
    };
    $clientRows = [[
        'id' => $view['id'], 'project' => $view['organization'], 'organization' => $view['organization'],
        'amount' => $view['amount'], 'status' => $view['status'], 'date' => $view['created_at'], 'review' => [],
    ]];
    $budgetConfig = [
        'catalog' => collect(app(\App\Services\FundingRules::class)->priceCatalog())->groupBy('group_label')->map->values()->all(),
        'rows' => array_map(fn ($item) => ['ref' => $item['ref'] ?? '', 'name' => $item['name'], 'qty' => $item['qty'], 'unit_price' => $item['unit_price']], $view['budget']['items']),
        'people' => $people,
        'cap' => $cap,
    ];
    $clientData = ['budget' => $budgetConfig, 'people' => $people, 'routes' => [
        'action' => route('admin.fund-request.action', ['id' => '__ID__']),
        'recommendation' => route('admin.fund-request.recommendation', ['id' => '__ID__']),
    ], 'requestId' => $view['id']];
    $fileLink = fn (array $file) => $file['url']
        ? ($file['is_image']
            ? '<a href="' . e($file['url']) . '" target="_blank" rel="noopener"><img src="' . e($file['url']) . '" alt="' . e($file['label']) . '" class="admin-document-thumb" style="max-height:140px"></a>'
            : '<a class="admin-button" href="' . e($file['url']) . '" target="_blank" rel="noopener">Open ' . e(strtoupper($file['extension'] ?? 'file')) . '</a>')
        : '<span class="admin-status rejected">Missing</span>';
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
        <p>{{ $view['category'] }} · {{ $view['beneficiary_count'] }} people · {{ $view['amount'] > 0 ? $peso($view['amount']) : 'budget not set' }} · submitted {{ $day($view['created_at']) }}</p>
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

{{-- Next step --}}
<article class="admin-card" style="margin-bottom:18px">
    <div class="admin-card-head"><div><h3>Next step</h3><p>{{ $stage['label'] }}</p></div></div>
    <div class="admin-card-body">
        @if ($stage['step'] === 1)
            <div class="admin-grid admin-two-col">
                <form method="POST" action="{{ route('admin.fund-request.interview', $view['id']) }}" class="admin-post-form">
                    @csrf
                    <strong>{{ empty($assessment['interview_at']) ? 'Schedule the interview' : 'Reschedule the interview' }}</strong>
                    <p class="admin-field-hint" style="margin:0">Assessment due by {{ $day($view['assessment_due']) }} ({{ config('funding.assessment_days') }} days from submission).</p>
                    <div class="admin-field"><label for="interview_at">Date & time</label><input id="interview_at" type="datetime-local" name="interview_at" required value="{{ old('interview_at', isset($assessment['interview_at']) ? \Carbon\Carbon::parse($assessment['interview_at'])->format('Y-m-d\TH:i') : '') }}"></div>
                    <div class="admin-field"><label for="social_worker">Social worker</label><input id="social_worker" name="social_worker" required maxlength="120" value="{{ old('social_worker', $assessment['social_worker'] ?? '') }}"></div>
                    <div class="admin-field"><label for="mode">Interview type</label><select id="mode" name="mode" class="admin-select" style="width:100%">@foreach (config('funding.assessment_modes') as $value => $text)<option value="{{ $value }}" @selected(old('mode', $assessment['mode'] ?? '') === $value)>{{ $text }}</option>@endforeach</select></div>
                    <div class="admin-field"><label for="location">Location / link <span style="font-weight:400">(optional)</span></label><input id="location" name="location" maxlength="255" value="{{ old('location', $assessment['location'] ?? '') }}"></div>
                    <div><button class="admin-button primary" type="submit">{{ empty($assessment['interview_at']) ? 'Schedule & notify requester' : 'Reschedule & notify' }}</button></div>
                </form>
                <form method="POST" action="{{ route('admin.fund-request.assessment', $view['id']) }}" class="admin-post-form">
                    @csrf
                    <strong>Record the assessment</strong>
                    <p class="admin-field-hint" style="margin:0">After the interview, record the social worker's findings. Approval requires "recommended".</p>
                    <div class="admin-field"><label for="outcome">Outcome</label><select id="outcome" name="outcome" class="admin-select" style="width:100%"><option value="recommended" @selected(old('outcome') === 'recommended')>Recommended for assistance</option><option value="not_recommended" @selected(old('outcome') === 'not_recommended')>Not recommended</option></select></div>
                    <div class="admin-field"><label for="verified_beneficiaries">People verified as qualified</label><input id="verified_beneficiaries" type="number" name="verified_beneficiaries" min="0" max="{{ config('funding.beneficiaries.max') }}" required value="{{ old('verified_beneficiaries', $view['beneficiary_count']) }}"></div>
                    <div class="admin-field"><label for="assessment_sw">Assessed by (social worker)</label><input id="assessment_sw" name="social_worker" maxlength="120" value="{{ old('social_worker', $assessment['social_worker'] ?? '') }}"></div>
                    <div class="admin-field"><label for="assessment_notes">Findings</label><textarea id="assessment_notes" name="notes" class="admin-textarea" style="min-height:90px" required maxlength="5000">{{ old('notes') }}</textarea></div>
                    <div><button class="admin-button success" type="submit">Save assessment</button></div>
                </form>
            </div>
        @elseif ($stage['key'] === 'ready_for_decision')
            @php($verifiedDocs = collect($view['documents'])->where('required', true)->where('status', 'verified')->count())
            @php($requiredDocs = collect($view['documents'])->where('required', true)->count())
            <p style="margin-top:0">Social worker: <strong>{{ ($assessment['outcome'] ?? '') === 'recommended' ? 'Recommended' : 'Not recommended' }}</strong> · {{ $assessment['verified_beneficiaries'] ?? '—' }} of {{ $view['beneficiary_count'] }} people verified · Documents verified: <strong>{{ $verifiedDocs }} of {{ $requiredDocs }}</strong> · Budget: <strong>{{ $view['budget']['per_person'] ? $peso($view['budget']['per_person']) . ' per person' : 'not set' }}</strong></p>
            @if ($guideline !== null)<p>Guideline: {{ $people }} × {{ $peso($cap) }} per person = <strong>{{ $peso($guideline) }}</strong>. The AI recommendation also shares funds with the other {{ $pendingCount - 1 }} pending request(s).</p>@endif
            <div class="admin-actions">
                <button class="admin-button danger" data-decision-open="rejected" data-request-id="{{ $view['id'] }}">Reject</button>
                @if (($assessment['outcome'] ?? '') === 'recommended' && $view['budget']['per_person'] && $view['documents_verified'])
                    <button class="admin-button success" data-decision-open="approved" data-request-id="{{ $view['id'] }}">Approve with AI recommendation</button>
                @elseif (($assessment['outcome'] ?? '') === 'recommended' && ! $view['documents_verified'])
                    <a class="admin-button primary" href="#documents">Verify every document to approve</a>
                @elseif (($assessment['outcome'] ?? '') === 'recommended')
                    <a class="admin-button primary" href="#budget">Set the budget per person to approve</a>
                @endif
            </div>
        @elseif ($stage['key'] === 'awaiting_final')
            <p style="margin:0">Sent to the super admin: {{ $view['admin_review']['decision'] === 'approved' ? 'approve ' . $peso($view['admin_review']['amount']) : 'reject' }} ({{ $view['admin_review']['by'] ?? 'admin' }}, {{ $day($view['admin_review']['at']) }}).</p>
        @elseif ($stage['key'] === 'to_release')
            <form method="POST" action="{{ route('admin.fund-request.disbursement', $view['id']) }}" enctype="multipart/form-data" class="admin-form-grid">
                @csrf
                <input type="hidden" name="method" value="bank_transfer">
                <div class="admin-field"><label for="amount">Amount released (₱)</label><input id="amount" type="number" name="amount" step="0.01" min="1" max="{{ $view['granted'] ?? $view['amount'] }}" required value="{{ old('amount', $view['granted'] ?? $view['amount']) }}"></div>
                <div class="admin-field"><label for="released_at">Date released</label><input id="released_at" type="date" name="released_at" required max="{{ now()->toDateString() }}" value="{{ old('released_at', now()->toDateString()) }}"></div>
                <div class="admin-field"><label for="reference_no">Bank transfer reference no.</label><input id="reference_no" name="reference_no" maxlength="120" required value="{{ old('reference_no') }}"></div>
                <div class="admin-field"><label for="proof">Proof (bank transfer slip)</label><input id="proof" type="file" name="proof" required accept=".jpg,.jpeg,.png,.pdf" style="height:auto;padding:9px"></div>
                <div class="admin-field"><label for="release_notes">Notes <span style="font-weight:400">(optional)</span></label><input id="release_notes" name="notes" maxlength="2000" value="{{ old('notes') }}"></div>
                <div style="grid-column:1/-1">
                    <p class="admin-field-hint">Send by bank transfer to <strong>{{ $disbursement['bank_name'] }}</strong> · {{ $disbursement['account_name'] }} · <strong>{{ $disbursement['account_number'] }}</strong>. The account name must be the receiver's.</p>
                    <button class="admin-button success" type="submit">Record release & request liquidation</button>
                </div>
            </form>
        @elseif ($stage['key'] === 'released')
            <p style="margin:0">Released {{ $peso($disbursement['amount']) }} on {{ $day($disbursement['released_at']) }}. Waiting for the liquidation (receipts and list of recipients), due {{ $day($view['liquidation_due']) }}.</p>
        @elseif ($stage['key'] === 'liquidation_review')
            <form method="POST" action="{{ route('admin.fund-request.liquidation', $view['id']) }}" class="admin-post-form">
                @csrf
                <p style="margin:0">Spent <strong>{{ $peso($liquidation['amount_spent'] ?? 0) }}</strong> of {{ $peso($disbursement['amount']) }} released · {{ count($liquidation['receipt_files']) }} receipt file(s) · {{ count((array) ($liquidation['recipient_names'] ?? [])) }} recipient name(s). Review the files below.</p>
                <div class="admin-field"><label for="remarks">Remarks <span style="font-weight:400">(required when returning)</span></label><textarea id="remarks" name="remarks" class="admin-textarea" style="min-height:70px" maxlength="2000">{{ old('remarks') }}</textarea></div>
                <div class="admin-actions"><button class="admin-button warning" name="decision" value="returned">Return for corrections</button><button class="admin-button success" name="decision" value="verified">Verify & complete</button></div>
            </form>
        @elseif ($stage['key'] === 'liquidation_returned')
            <p style="margin:0">Returned to the requester: “{{ $liquidation['review_remarks'] }}”. Waiting for a corrected liquidation.</p>
        @elseif ($stage['key'] === 'completed')
            <p style="margin:0">Completed. The liquidation was verified on {{ $day($liquidation['reviewed_at'] ?? null) }}.</p>
        @else
            <p style="margin:0">{{ $view['final_notes'] ?: ($view['admin_review']['notes'] ?? 'No further action.') }}</p>
        @endif
    </div>
</article>

<div class="admin-grid admin-two-col">
    <article class="admin-card">
        <div class="admin-card-head"><div><h3>Social worker assessment</h3><p>Interview within {{ config('funding.assessment_days') }} days of submission</p></div></div>
        <div class="admin-card-body admin-list">
            <div class="admin-list-row"><div class="admin-list-copy"><strong>Interview</strong><span>{{ $dayTime($assessment['interview_at'] ?? null) }}@if (!empty($assessment['mode_label'])) · {{ $assessment['mode_label'] }}@endif @if (!empty($assessment['location'])) · {{ $assessment['location'] }}@endif</span></div></div>
            @if (!empty($assessment['interview_at']))
                @php($answer = $view['interview_response'])
                <div class="admin-list-row"><div class="admin-list-copy"><strong>Requester's answer</strong><span>
                    @if (! $answer) Waiting for the requester to confirm
                    @elseif ($answer['status'] === 'confirmed') <span class="admin-status approved">Confirmed</span> {{ $dayTime($answer['responded_at'] ?? null) }}
                    @else <span class="admin-status">Asked for another schedule</span> Prefers {{ $dayTime($answer['preferred_at'] ?? null) }} — “{{ $answer['note'] }}”. Reschedule above and the requester is notified.
                    @endif
                </span></div></div>
            @endif
            <div class="admin-list-row"><div class="admin-list-copy"><strong>Social worker</strong><span>{{ $assessment['social_worker'] ?? '—' }}</span></div></div>
            <div class="admin-list-row"><div class="admin-list-copy"><strong>Outcome</strong><span>@if (!empty($assessment['assessed_at'])){{ ($assessment['outcome'] ?? '') === 'recommended' ? 'Recommended' : 'Not recommended' }} · {{ $assessment['verified_beneficiaries'] ?? '—' }} people verified · {{ $day($assessment['assessed_at']) }}@else Not yet assessed @endif</span></div></div>
            @if (!empty($assessment['notes']))<div class="admin-list-row"><div class="admin-list-copy"><strong>Findings</strong><span style="white-space:pre-line">{{ $assessment['notes'] }}</span></div></div>@endif
        </div>
    </article>

    <article class="admin-card" id="ai">
        <div class="admin-card-head"><div><h3>AI assessment</h3><p>{{ $view['ai']['provider'] ?? 'Free AI provider chain' }}</p></div>
            <form method="POST" action="{{ route('admin.fund-request.ai-score', $view['id']) }}">@csrf<button class="admin-button" type="submit">{{ $view['ai'] ? '↻ Re-run' : 'Run AI assessment' }}</button></form></div>
        <div class="admin-card-body">
            @if ($view['ai'])
                <p style="margin-top:0"><strong style="font-size:22px">{{ $view['ai']['score'] }}</strong>/100 · <span class="admin-status {{ ($view['ai']['recommendation'] ?? '') === 'critical' ? 'critical' : 'in-progress' }}">{{ $view['ai']['recommendation'] ?? 'scored' }}</span></p>
                @foreach ($view['ai']['breakdown'] as $criterion => $score)
                    <div class="admin-allocation-row" style="margin-bottom:8px"><div><span>{{ ucwords(str_replace('_', ' ', $criterion)) }}</span><b>{{ $score }}</b></div><div class="admin-progress"><span style="width:{{ $score }}%"></span></div></div>
                @endforeach
                <p>{{ $view['ai']['reasoning'] }}</p>
                @if ($view['ai']['red_flags'])<p style="margin-bottom:4px"><strong>Verify during the interview:</strong></p><ul style="margin:0;padding-left:18px;font-size:12px">@foreach ($view['ai']['red_flags'] as $flag)<li>{{ $flag }}</li>@endforeach</ul>@endif
                <p class="admin-field-hint">Scored {{ $dayTime($view['ai']['scored_at']) }}. AI output is advisory; the social worker's assessment decides.</p>
            @else
                <p style="margin:0">Not scored yet. Scoring runs automatically after submission when an AI API key is configured.</p>
            @endif
        </div>
    </article>
</div>

<div class="admin-grid admin-two-col">
    <article class="admin-card" id="documents">
        <div class="admin-card-head"><div><h3>Requirements</h3><p>Formal request letter and Certificate of Indigency · verify each before approving</p></div><span class="admin-status {{ $view['documents_verified'] ? 'verified' : 'pending-review' }}">{{ $view['documents_verified'] ? 'All verified' : 'Review needed' }}</span></div>
        <div class="admin-card-body admin-form-grid">
            @foreach ($view['documents'] as $document)
                @php($statusText = ['verified' => 'Verified', 'resubmit' => 'Resubmission requested', 'pending' => 'To review', 'missing' => 'Missing'][$document['status'] ?? ''] ?? null)
                <div class="admin-doc">
                    <div class="admin-doc-head"><label>{{ $document['label'] }}@if ($document['required']) <span style="color:#dc2626">*</span>@endif</label>@if ($statusText)<span class="admin-status {{ $document['status'] === 'pending' ? 'pending-review' : $document['status'] }}">{{ $statusText }}</span>@endif</div>
                    {!! $fileLink($document) !!}
                    @if (!empty($document['remarks']) && $document['status'] === 'resubmit')<p class="admin-field-hint">Asked: “{{ $document['remarks'] }}”</p>@endif
                    @if (! empty($document['field']) && $document['url'] && $view['status'] === 'pending' && in_array($document['status'], ['pending', 'verified'], true))
                        <form method="POST" action="{{ route('admin.fund-request.document-review', [$view['id'], $document['field']]) }}">
                            @csrf
                            @if ($document['status'] !== 'verified')<button class="admin-button success" name="decision" value="verified">Verify</button>@endif
                            <input name="remarks" class="admin-input" maxlength="1000" placeholder="What is wrong? (to ask for a new file)">
                            <button class="admin-button warning" name="decision" value="resubmit">Ask to resubmit</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </article>

    <article class="admin-card" id="budget">
        <div class="admin-card-head"><div><h3>Budget per person</h3><p>Choose items and quantities from the price list. Prices and items are managed by the super admin.</p></div><strong>{{ $view['budget']['per_person'] ? $peso($view['budget']['per_person']) . ' / person' : 'Not set' }}</strong></div>
        @if ($view['status'] === 'pending')
            <form method="POST" action="{{ route('admin.fund-request.budget', $view['id']) }}" data-budget-builder>
                @csrf
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Item</th><th style="width:90px">Qty / person</th><th style="width:120px">Unit price (₱)</th><th style="width:100px">Subtotal</th><th style="width:40px"></th></tr></thead><tbody data-budget-rows></tbody></table></div>
                <div class="admin-card-body">
                    <div class="admin-actions" style="margin-bottom:12px">
                        <select class="admin-select" data-budget-picker aria-label="Add an item from the price list" style="flex:1;min-width:220px"><option value="">+ Add item from the price list…</option></select>
                        <a class="admin-button" href="{{ route('admin') }}#reports-prices">View price list</a>
                    </div>
                    <p style="margin:0">{{ $people }} {{ isset($assessment['verified_beneficiaries']) ? 'verified' : 'listed' }} people × <strong data-budget-per-person>₱0.00</strong> = <strong data-budget-total>₱0.00</strong></p>
                    <p class="admin-field-hint" style="margin-top:6px">Item missing from the list? Ask the super admin to add it; admins cannot add items or change prices.</p>
                    @if ($guideline !== null)<p class="admin-field-hint">Foundation guideline: about {{ $peso($cap) }} per person ({{ $peso($guideline) }} for {{ $people }} people). <span data-budget-cap-note></span></p>@endif
                    <div class="admin-actions" style="margin-top:12px"><button class="admin-button primary" type="submit">Save budget</button></div>
                </div>
            </form>
        @else
            @if ($view['budget']['items'])
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Reference</th><th>Subtotal</th></tr></thead><tbody>
                    @foreach ($view['budget']['items'] as $item)
                        <tr><td>{{ $item['name'] }}</td><td>{{ $item['qty'] }}</td><td>{{ $peso($item['unit_price']) }}</td>
                            <td>@if ($item['check'])<span class="admin-status {{ $item['check'] === 'above' ? 'rejected' : ($item['check'] === 'within' ? 'approved' : 'cancelled') }}">{{ $item['check'] }}</span><small>{{ $peso($item['reference_min']) }}–{{ $peso($item['reference_max']) }}</small>@else<small>custom item</small>@endif</td>
                            <td>{{ $peso($item['subtotal']) }}</td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
            <div class="admin-card-body">
                <p style="margin:0">{{ $view['budget']['people'] ?? $people }} people × {{ $view['budget']['per_person'] ? $peso($view['budget']['per_person']) : '—' }} = <strong>{{ $peso($view['amount']) }}</strong></p>
                @if ($view['granted'] !== null)<p style="margin-bottom:0">Approved: <strong>{{ $peso($view['granted']) }}</strong></p>@endif
            </div>
        @endif
    </article>
</div>

<div class="admin-grid admin-two-col">
    <article class="admin-card">
        <div class="admin-card-head"><div><h3>Purpose & beneficiaries</h3><p>{{ count($view['beneficiaries']) }} listed @if (isset($assessment['verified_beneficiaries'])) · {{ $assessment['verified_beneficiaries'] }} verified @endif</p></div></div>
        <div class="admin-card-body">
            <p style="margin-top:0;white-space:pre-line">{{ $view['purpose'] }}</p>
            @if ($view['beneficiaries'])
                <details><summary style="cursor:pointer;font-size:12px;font-weight:700">Show list of people</summary>
                    <ol style="columns:2;font-size:12px;padding-left:20px">@foreach ($view['beneficiaries'] as $name)<li>{{ $name }}</li>@endforeach</ol>
                </details>
            @endif
        </div>
    </article>

    <article class="admin-card">
        <div class="admin-card-head"><div><h3>Release of funds</h3><p>{{ $disbursement['method_label'] }}</p></div></div>
        <div class="admin-card-body admin-list">
            @if ($disbursement['bank_name'])
                <div class="admin-list-row"><div class="admin-list-copy"><strong>Bank account</strong><span>{{ $disbursement['bank_name'] }} · {{ $disbursement['account_name'] }} · {{ $disbursement['account_number'] }}</span></div></div>
            @endif
            @if ($disbursement['released_at'])
                <div class="admin-list-row"><div class="admin-list-copy"><strong>Released</strong><span>{{ $peso($disbursement['amount']) }} · {{ $day($disbursement['released_at']) }} · ref. {{ $disbursement['reference_no'] ?? '—' }} · by {{ $disbursement['recorded_by'] ?? 'admin' }}</span></div>@if ($disbursement['proof'])<div class="admin-list-meta"><a class="admin-button" href="{{ $disbursement['proof']['url'] }}" target="_blank" rel="noopener">Proof</a></div>@endif</div>
            @endif
            @foreach ($view['contact'] as $label => $value)@if ($value)<div class="admin-list-row"><div class="admin-list-copy"><strong>{{ $label }}</strong><span>{{ $value }}</span></div></div>@endif @endforeach
        </div>
    </article>
</div>

<div class="admin-grid admin-two-col">
    @if ($liquidation)
        <article class="admin-card">
            <div class="admin-card-head"><div><h3>Liquidation</h3><p>Submitted {{ $day($liquidation['submitted_at'] ?? null) }} · {{ $liquidation['status'] }}</p></div></div>
            <div class="admin-card-body">
                <p style="margin-top:0">Amount spent: <strong>{{ $peso($liquidation['amount_spent'] ?? 0) }}</strong>@if (!empty($liquidation['notes'])) · {{ $liquidation['notes'] }}@endif</p>
                <div class="admin-form-grid">
                    @foreach ($liquidation['receipt_files'] as $i => $file)<div class="admin-field"><label>Receipt {{ $i + 1 }}</label>{!! $fileLink($file) !!}</div>@endforeach
                    @if ($liquidation['recipient_file'])<div class="admin-field"><label>List of recipients</label>{!! $fileLink($liquidation['recipient_file']) !!}</div>@endif
                    @foreach ($liquidation['photo_files'] as $i => $file)<div class="admin-field"><label>Photo {{ $i + 1 }}</label>{!! $fileLink($file) !!}</div>@endforeach
                </div>
                @if (!empty($liquidation['recipient_names']))
                    <details style="margin-top:12px"><summary style="cursor:pointer;font-size:12px;font-weight:700">Recipients named ({{ count($liquidation['recipient_names']) }})</summary><ol style="columns:2;font-size:12px;padding-left:20px">@foreach ($liquidation['recipient_names'] as $name)<li>{{ $name }}</li>@endforeach</ol></details>
                @endif
            </div>
        </article>
    @endif
    <article class="admin-card">
        <div class="admin-card-head"><div><h3>History</h3><p>Every step on this request</p></div></div>
        <div class="admin-card-body admin-history" style="margin-top:0">
            @foreach ($view['timeline'] as $event)
                <div class="admin-timeline-row"><strong>{{ $event['label'] }}</strong><span>{{ $dayTime($event['at']) }}@if ($event['detail']) · {{ $event['detail'] }}@endif</span></div>
            @endforeach
        </div>
    </article>
</div>

<article class="admin-card" id="notes">
    <div class="admin-card-head"><div><h3>Verification notes</h3><p>For the foundation only: the super admin sees these when finalizing; the requester never does.</p></div><span class="admin-status in-progress">{{ count($view['staff_notes']) }} note(s)</span></div>
    <div class="admin-card-body">
        @forelse ($view['staff_notes'] as $note)
            <div class="admin-timeline-row"><strong>{{ $note['author_name'] ?? 'Admin' }} · {{ $dayTime($note['at'] ?? null) }}</strong><span style="white-space:pre-line">{{ $note['body'] }}</span></div>
        @empty
            <p style="margin-top:0">No notes yet. Record what you verified, for example a document you confirmed with the barangay, or anything the super admin should know.</p>
        @endforelse
        <form method="POST" action="{{ route('admin.fund-request.notes', $view['id']) }}" class="admin-post-form" style="margin-top:14px">
            @csrf
            <label for="staff-note" class="sr-only">Verification note</label>
            <textarea id="staff-note" name="note" class="admin-textarea" style="min-height:80px" maxlength="2000" required placeholder="Add a verification note…">{{ old('note') }}</textarea>
            <div class="admin-actions"><button class="admin-button primary" type="submit">Add note</button></div>
        </form>
    </div>
</article>

<article class="admin-card" id="messages" data-thread="{{ route('fund-request.messages', $view['id']) }}">
    <div class="admin-card-head"><div><h3>Conversation with the requester</h3><p>Arrange the interview, ask about the assessment, or explain a document that needs resubmitting. {{ $view['contact']['Contact person'] ?? 'The requester' }} is notified of each message.</p></div></div>
    <div class="admin-card-body">
        <div class="admin-thread" data-thread-list>@include('adminPage.partials.messages', ['thread' => $thread, 'side' => 'staff'])</div>
        <form class="admin-thread-form" method="POST" action="{{ route('fund-request.messages.store', $view['id']) }}" data-thread-form>
            @csrf
            <textarea name="body" class="admin-textarea" maxlength="2000" required placeholder="Write a message… (Ctrl+Enter to send)"></textarea>
            <button class="admin-button primary" type="submit">Send</button>
        </form>
    </div>
</article>
@endsection
