@extends('layouts.dashboard')
@php
    $titles = ['request-status' => ['Every step, in one place.', 'Follow your funding requests from submission to a decision.'], 'activity' => ['Your impact, over time.', 'A closer look at your funding journey.']];
    $labels = ['request-status' => 'Request status', 'activity' => 'My activity'];
    $requests = $requests ?? [];
    $maxAppeals = \App\Services\FundingService::MAX_APPEALS;
@endphp
@section('title', $labels[$screen].' - Gift of Hope')
@section('content')
<div class="hope-page">
    <div class="hope-heading"><div><div class="hope-eyebrow">{{ strtoupper($labels[$screen]) }}</div><h1>{{ $titles[$screen][0] }}</h1><p>{{ $titles[$screen][1] }}</p></div><a class="hope-button" href="{{ route('fund-request') }}">＋ Create fund request</a></div>

    @if($screen === 'request-status')
        <div class="hope-grid four">@foreach($requestCounts ?? ['Total requests' => count($requests), 'Pending review' => 0, 'Approved / completed' => 0, 'Denied' => 0] as $label => $value)<div class="hope-card hope-stat"><span>{{ $label }}</span><strong>{{ $value }}</strong><span>Your requests</span></div>@endforeach</div>
        <section class="hope-card hope-section"><div class="hope-toolbar"><input type="search" data-filter-search aria-label="Search requests" placeholder="Search title or request ID…"><select data-filter-select aria-label="Filter request status"><option value="">All statuses</option>@foreach(['Pending', 'Approved', 'Denied', 'Completed'] as $status)<option>{{ $status }}</option>@endforeach</select></div><div class="hope-table-wrap"><table class="hope-table"><thead><tr><th>Request</th><th>Amount</th><th>Last update</th><th>Status</th><th>Details</th></tr></thead><tbody>
        @foreach($requests as $i => $item)<tr data-filter-item data-category="{{ $item['status'] }}"><td><strong>{{ $item['title'] }}</strong><small>{{ $item['id'] }} · {{ $item['category'] }}</small></td><td>{{ $item['amount'] > 0 ? '₱' . number_format($item['amount']) : 'To be decided' }}</td><td><time datetime="{{ $item['date'] }}">{{ date('M d, Y', strtotime($item['date'])) }}</time></td><td><span class="hope-badge {{ $item['color'] }}">{{ $item['status'] }}</span><small>{{ $item['stage'] }}</small></td><td><button class="hope-text-button" data-open-dialog="request-{{ $i }}">View timeline →</button></td></tr>@endforeach
        </tbody></table></div><p class="hope-empty" data-filter-empty hidden>No requests match your filters.</p></section>
        @foreach($requests as $i => $item)
        <dialog class="hope-dialog" id="request-{{ $i }}" aria-labelledby="request-title-{{ $i }}"><button class="hope-close" data-close-dialog aria-label="Close">×</button><span class="hope-badge {{ $item['color'] }}">{{ $item['status'] }}</span><h2 id="request-title-{{ $i }}" style="margin-top:18px">{{ $item['title'] }}</h2><p>{{ $item['id'] }} · {{ $item['amount'] > 0 ? '₱' . number_format($item['amount']) : 'Budget to be decided' }}</p><p><strong>{{ $item['stage'] }}</strong></p><ol class="hope-timeline">@foreach($item['timeline'] as $event)<li><strong>{{ $event['label'] }}</strong><small>{{ \Carbon\Carbon::parse($event['at'])->format('M d, Y · g:i A') }}@if($event['detail']) · {{ $event['detail'] }}@endif</small></li>@endforeach</ol><div class="hope-actions"><a class="hope-button secondary" href="{{ route('fund-request.show', $item['id']) }}">Open full details →</a></div>
        @if($item['status'] === 'Denied')<div class="hope-preview"><strong>Reason for denial</strong><br>{{ $item['reason'] ?: 'No reason was recorded. Please contact the foundation.' }}</div><p>Appeals used: {{ $item['appeals'] }} of {{ $maxAppeals }}.</p>@if($item['appeals'] < $maxAppeals)<button class="hope-button" data-switch-dialog="appeal-{{ $i }}">Re-appeal request</button>@else<p class="hope-error" role="status">Appeal limit reached. A request can be appealed at most {{ $maxAppeals }} times.</p><button class="hope-button" disabled>Appeal unavailable</button>@endif @endif
        </dialog>
        @if($item['status'] === 'Denied' && $item['appeals'] < $maxAppeals)
        <dialog class="hope-dialog" id="appeal-{{ $i }}" aria-labelledby="appeal-title-{{ $i }}"><button class="hope-close" data-close-dialog aria-label="Close">×</button><h2 id="appeal-title-{{ $i }}">Re-appeal your request</h2><p>The foundation will review the request again. You can appeal at most {{ $maxAppeals }} times; this is appeal {{ $item['appeals'] + 1 }}.</p><form method="POST" action="{{ route('fund-request.appeal', $item['id']) }}" enctype="multipart/form-data" class="hope-section">@csrf<div class="hope-field"><label for="appeal-reason-{{ $i }}">What has changed?</label><textarea id="appeal-reason-{{ $i }}" name="reason" rows="4" required maxlength="2000" placeholder="Explain what is different now, or what the foundation may have missed…"></textarea></div><div class="hope-field"><label for="appeal-file-{{ $i }}">Updated supporting document</label><input id="appeal-file-{{ $i }}" name="document" type="file" required accept=".jpg,.jpeg,.png,.pdf" data-validate-file data-max-mb="10"><small>JPG, PNG or PDF, up to 10 MB.</small></div><p class="hope-error" data-form-error role="alert"></p><div class="hope-actions"><button class="hope-button" type="submit">Send appeal</button><button class="hope-button secondary" type="button" data-switch-dialog="request-{{ $i }}">Back to status</button></div></form></dialog>
        @endif
        @endforeach

    @elseif($screen === 'activity')
        @php($activityData = $activityData ?? ['totalRequests' => 0, 'receivedAmount' => 0, 'deniedRequests' => 0, 'successRate' => 0, 'months' => [], 'maxChartAmount' => 1])
        <div class="hope-grid four">
            <div class="hope-card hope-stat"><span>Funds received</span><strong>₱{{ number_format($activityData['receivedAmount']) }}</strong><span>Released to you by the foundation</span></div>
            <div class="hope-card hope-stat"><span>Total requests</span><strong>{{ $activityData['totalRequests'] }}</strong><span>Submitted by your account</span></div>
            <div class="hope-card hope-stat"><span>Denied requests</span><strong>{{ $activityData['deniedRequests'] }}</strong><span>Not approved after review</span></div>
            <div class="hope-card hope-stat"><span>Success rate</span><strong>{{ $activityData['successRate'] }}%</strong><span>Approved or completed requests</span></div>
        </div>
        <section class="hope-card hope-section">
            <div class="hope-heading"><div><h2>Funds received</h2><p>Monthly totals of funds released to you</p></div><span class="hope-badge">Last 6 months</span></div>
            <div class="hope-table-wrap"><table class="hope-table"><caption class="sr-only">Monthly funds received</caption><thead><tr><th>Month</th><th>Received</th></tr></thead><tbody>
                @forelse($activityData['months'] as $month)<tr><td>{{ $month['label'] }}</td><td>₱{{ number_format($month['amount']) }}</td></tr>@empty<tr><td colspan="2">No funds released yet.</td></tr>@endforelse
            </tbody></table></div>
        </section>
        <div class="hope-grid two hope-section"><section class="hope-card"><h2>Your request journey</h2><p>{{ $activityData['totalRequests'] }} request(s), including {{ $activityData['deniedRequests'] }} denied.</p><div class="hope-actions"><a class="hope-button secondary" href="{{ route('request-status') }}">Explore request history →</a></div></section><section class="hope-card"><h2>Keep the hope going</h2><p>Start another funding request when you are eligible.</p><div class="hope-actions"><a class="hope-button secondary" href="{{ route('fund-request') }}">Create fund request →</a></div></section></div>
    @endif
</div>
@endsection
