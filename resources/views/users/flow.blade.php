@extends('layouts.dashboard')
@php
    $titles = ['groups' => ['Find your people.', 'Join a community that shares your passion for helping others.'], 'fundraisers' => ['Hope in action.', 'Explore the causes our community is bringing to life.'], 'request-status' => ['Every step, in one place.', 'Follow your funding requests from submission to a decision.'], 'notifications' => ['You’re up to date.', 'Updates from your requests and your community.'], 'activity' => ['Your impact, over time.', 'A closer look at your funding journey.']];
    $labels = ['groups' => 'Groups', 'fundraisers' => 'Fundraisers', 'request-status' => 'Request status', 'notifications' => 'Notifications', 'activity' => 'My activity'];
    $groups = [
        ['name' => 'Education for Everyone', 'category' => 'Education', 'icon' => '▤', 'color' => '', 'members' => 128, 'about' => 'Opening doors through school supplies, scholarships, and community learning programs.', 'leaders' => 'Maria Santos · Coordinator; Juan Dela Cruz · Volunteer lead', 'fund' => 'Back-to-school essentials', 'raised' => 32500, 'target' => 50000],
        ['name' => 'Community Care Circle', 'category' => 'Healthcare', 'icon' => '♡', 'color' => 'green', 'members' => 86, 'about' => 'Helping families access essential healthcare and building a healthier, kinder community.', 'leaders' => 'Ana Reyes · Coordinator; Carlo Garcia · Volunteer lead', 'fund' => 'Care for every family', 'raised' => 18000, 'target' => 40000],
        ['name' => 'Neighbors Giving Hope', 'category' => 'Food & Shelter', 'icon' => '⌂', 'color' => 'gold', 'members' => 204, 'about' => 'Bringing food, essential supplies, and a helping hand to neighbors who need them most.', 'leaders' => 'Patricia Cruz · Coordinator; Miguel Ramos · Volunteer lead', 'fund' => 'A meal, a little hope', 'raised' => 25000, 'target' => 25000],
    ];
    $requests = [
        ['id' => 'GOH-2026-014', 'title' => 'School supplies for the new term', 'category' => 'Education', 'amount' => 15000, 'status' => 'Pending', 'color' => 'gold', 'date' => '2026-09-10', 'appeals' => 0],
        ['id' => 'GOH-2026-012', 'title' => 'Community medical assistance', 'category' => 'Healthcare', 'amount' => 20000, 'status' => 'Approved', 'color' => 'green', 'date' => '2026-09-08', 'appeals' => 0],
        ['id' => 'GOH-2026-009', 'title' => 'Emergency home repairs', 'category' => 'Food & Shelter', 'amount' => 12000, 'status' => 'Denied', 'color' => 'red', 'date' => '2026-09-05', 'appeals' => 1],
        ['id' => 'GOH-2026-006', 'title' => 'Community pantry supplies', 'category' => 'Food & Shelter', 'amount' => 8000, 'status' => 'Completed', 'color' => 'green', 'date' => '2026-08-24', 'appeals' => 0],
        ['id' => 'GOH-2026-003', 'title' => 'Learning center equipment', 'category' => 'Education', 'amount' => 10000, 'status' => 'Denied', 'color' => 'red', 'date' => '2026-08-12', 'appeals' => 2],
    ];
@endphp
@section('title', $labels[$screen].' - Gift of Hope')
@section('content')
<div class="hope-page">
    <div class="hope-heading"><div><div class="hope-eyebrow">{{ strtoupper($labels[$screen]) }}</div><h1>{{ $titles[$screen][0] }}</h1><p>{{ $titles[$screen][1] }}</p></div><a class="hope-button" href="{{ route('fund-request') }}">＋ Create fund request</a></div>
    <div class="hope-preview"><strong>UI preview</strong> · The records on this page are examples. Preview actions do not send requests or change account records.</div>

    @if(in_array($screen, ['groups', 'fundraisers']))
        <div class="hope-toolbar"><input type="search" data-filter-search aria-label="Search {{ $labels[$screen] }}" placeholder="Search {{ strtolower($labels[$screen]) }}…"><select data-filter-select aria-label="Filter category"><option value="">All categories</option><option>Education</option><option>Healthcare</option><option>Food &amp; Shelter</option></select></div>
        <div class="hope-grid">
        @foreach($groups as $i => $group)
            <article class="hope-card" data-filter-item data-category="{{ $group['category'] }}">
                <div class="hope-card-cover {{ $group['color'] }}" aria-hidden="true">{{ $group['icon'] }}</div>
                <span class="hope-badge {{ $group['color'] }}">{{ $group['category'] }}</span>
                <h2 style="margin-top:15px">{{ $screen === 'groups' ? $group['name'] : $group['fund'] }}</h2>
                <p>{{ $group['about'] }}</p>
                @if($screen === 'groups')
                    <div class="hope-meta"><span>◎ {{ $group['members'] }} members</span><span>Community group</span></div>
                    <div class="hope-actions"><button class="hope-button secondary" data-open-dialog="group-{{ $i }}">View group</button><button class="hope-button" data-join="{{ $i }}">Join group</button></div>
                @else
                    <div class="hope-meta"><strong>₱{{ number_format($group['raised']) }} raised</strong><span>{{ round($group['raised'] / $group['target'] * 100) }}%</span></div>
                    <progress class="hope-progress" value="{{ $group['raised'] }}" max="{{ $group['target'] }}" aria-label="{{ $group['fund'] }} funding progress"></progress>
                    <div class="hope-meta"><span>of ₱{{ number_format($group['target']) }} goal</span><span class="hope-badge {{ $group['raised'] === $group['target'] ? 'green' : '' }}">{{ $group['raised'] === $group['target'] ? 'Goal reached' : 'Active' }}</span></div>
                    <p>Organized by <strong>{{ $group['name'] }}</strong></p>
                    <div class="hope-actions"><button class="hope-button secondary" data-open-dialog="group-{{ $i }}">View responsible group ↗</button></div>
                @endif
            </article>
        @endforeach
        </div>
        <p class="hope-empty" data-filter-empty hidden>No matches found. Try another search or category.</p>
        @foreach($groups as $i => $group)
        <dialog class="hope-dialog" id="group-{{ $i }}" aria-labelledby="group-title-{{ $i }}"><button class="hope-close" data-close-dialog aria-label="Close">×</button><span class="hope-badge">{{ $group['category'] }}</span><h2 id="group-title-{{ $i }}" style="margin-top:18px">{{ $group['name'] }}</h2><p>{{ $group['about'] }}</p><h3 class="hope-section">Key members</h3><p>{{ $group['leaders'] }}</p><h3 class="hope-section">Fundraiser responsibility</h3><p>This example group coordinates <strong>{{ $group['fund'] }}</strong>, with ₱{{ number_format($group['raised']) }} raised toward a ₱{{ number_format($group['target']) }} target.</p><div class="hope-actions"><button class="hope-button" data-join="{{ $i }}">Join group</button><button class="hope-button secondary" data-close-dialog>Close</button></div></dialog>
        @endforeach

    @elseif($screen === 'request-status')
        <div class="hope-grid four">@foreach(['Total requests' => '5', 'Pending review' => '1', 'Approved / completed' => '2', 'Denied' => '2'] as $label => $value)<div class="hope-card hope-stat"><span>{{ $label }}</span><strong>{{ $value }}</strong><span>Example requests</span></div>@endforeach</div>
        <section class="hope-card hope-section"><div class="hope-toolbar"><input type="search" data-filter-search aria-label="Search requests" placeholder="Search title or request ID…"><select data-filter-select aria-label="Filter request status"><option value="">All statuses</option>@foreach(['Pending', 'Approved', 'Denied', 'Completed'] as $status)<option>{{ $status }}</option>@endforeach</select></div><div class="hope-table-wrap"><table class="hope-table"><thead><tr><th>Request</th><th>Amount</th><th>Last update</th><th>Status</th><th>Details</th></tr></thead><tbody>
        @foreach($requests as $i => $item)<tr data-filter-item data-category="{{ $item['status'] }}"><td><strong>{{ $item['title'] }}</strong><small>{{ $item['id'] }} · {{ $item['category'] }}</small></td><td>₱{{ number_format($item['amount']) }}</td><td><time datetime="{{ $item['date'] }}">{{ date('M d, Y', strtotime($item['date'])) }}</time></td><td><span class="hope-badge {{ $item['color'] }}">{{ $item['status'] }}</span></td><td><button class="hope-text-button" data-open-dialog="request-{{ $i }}">View timeline →</button></td></tr>@endforeach
        </tbody></table></div><p class="hope-empty" data-filter-empty hidden>No requests match your filters.</p></section>
        @foreach($requests as $i => $item)
        <dialog class="hope-dialog" id="request-{{ $i }}" aria-labelledby="request-title-{{ $i }}"><button class="hope-close" data-close-dialog aria-label="Close">×</button><span class="hope-badge {{ $item['color'] }}">{{ $item['status'] }}</span><h2 id="request-title-{{ $i }}" style="margin-top:18px">{{ $item['title'] }}</h2><p>{{ $item['id'] }} · ₱{{ number_format($item['amount']) }}</p><ol class="hope-timeline"><li><strong>Request submitted</strong><small>{{ $item['date'] }} · 9:00 AM</small></li><li><strong>Documents received for review</strong><small>{{ $item['date'] }} · 9:05 AM</small></li><li><strong>{{ $item['status'] === 'Pending' ? 'Awaiting a decision' : $item['status'] }}</strong><small>{{ $item['date'] }} · 2:30 PM</small></li></ol>
        @if($item['status'] === 'Denied')<div class="hope-preview"><strong>Reason for denial</strong><br>The supporting document does not clearly show the requested expenses. Please provide an updated, readable document.</div><p>Appeals used: {{ $item['appeals'] }} of 2.</p>@if($item['appeals'] < 2)<button class="hope-button" data-switch-dialog="appeal-{{ $i }}">Re-appeal request</button>@else<p class="hope-error" role="status">Re-appeal limit reached. A maximum of two appeals is allowed.</p><button class="hope-button" disabled>Appeal unavailable</button>@endif @endif
        </dialog>
        @if($item['status'] === 'Denied' && $item['appeals'] < 2)
        <dialog class="hope-dialog" id="appeal-{{ $i }}" aria-labelledby="appeal-title-{{ $i }}"><button class="hope-close" data-close-dialog aria-label="Close">×</button><h2 id="appeal-title-{{ $i }}">Re-appeal your request</h2><p>Your previous details are filled in below. Update the information and attach clearer documents.</p><form data-preview-form="Appeal preview completed. No appeal was sent." class="hope-section"><div class="hope-field"><label for="appeal-name-{{ $i }}">Request title</label><input id="appeal-name-{{ $i }}" value="{{ $item['title'] }}" required maxlength="255"></div><div class="hope-field"><label for="appeal-amount-{{ $i }}">Target amount (₱)</label><input id="appeal-amount-{{ $i }}" type="number" min="1" step="0.01" value="{{ $item['amount'] }}" required></div><div class="hope-field"><label for="appeal-reason-{{ $i }}">Reason for re-appeal</label><textarea id="appeal-reason-{{ $i }}" rows="3" required placeholder="Explain what has changed…"></textarea></div><div class="hope-field"><label for="appeal-file-{{ $i }}">Updated supporting document</label><input id="appeal-file-{{ $i }}" type="file" required accept=".jpg,.jpeg,.png,.pdf" data-validate-file data-max-mb="50"><small>JPG, PNG or PDF · up to 50 MB · preview only</small></div><p class="hope-error" data-form-error role="alert"></p><div class="hope-actions"><button class="hope-button" type="submit">Preview re-appeal</button><button class="hope-button secondary" type="button" data-switch-dialog="request-{{ $i }}">Back to status</button></div></form></dialog>
        @endif
        @endforeach

    @elseif($screen === 'notifications')
        <section class="hope-card"><div class="hope-heading"><h2>Recent updates</h2><button class="hope-text-button" data-mark-read>Mark all as read</button></div>
        @foreach([['Request approved', 'Your community medical assistance request has been approved. View the request timeline for details.', '2026-09-08T14:30:00', 'Sep 8, 2026 · 2:30 PM', 'request-status'], ['More information needed', 'Your home repair request was denied. Review the reason and re-appeal with updated documents.', '2026-09-05T14:30:00', 'Sep 5, 2026 · 2:30 PM', 'request-status'], ['A community milestone', 'Neighbors Giving Hope has reached its fundraising goal. See the group behind this cause.', '2026-09-04T10:00:00', 'Sep 4, 2026 · 10:00 AM', 'fundraisers']] as [$title, $body, $iso, $date, $target])
        <article class="hope-notice"><span class="hope-avatar" aria-hidden="true">◉</span><div><h3>{{ $title }} <span class="hope-badge" data-unread>New</span></h3><p>{{ $body }}</p><time datetime="{{ $iso }}">{{ $date }}</time><div class="hope-actions"><a class="hope-text-button" href="{{ route($target) }}">View details →</a></div></div></article>
        @endforeach</section>

    @elseif($screen === 'activity')
        <div class="hope-grid four">@foreach(['Funds received' => '₱8,000', 'Total requests' => '5', 'Denied requests' => '2', 'Success rate' => '40%'] as $label => $value)<div class="hope-card hope-stat"><span>{{ $label }}</span><strong>{{ $value }}</strong><span>{{ $label === 'Success rate' ? '2 approved or completed / 5 requests' : 'Example activity' }}</span></div>@endforeach</div>
        <section class="hope-card hope-section"><div class="hope-heading"><div><h2>Funds received</h2><p>Monthly totals · Philippine peso (₱)</p></div><span class="hope-badge">Apr – Sep 2026</span></div>
        <svg class="hope-chart" viewBox="0 0 840 260" role="img" aria-labelledby="chart-title chart-description"><title id="chart-title">Monthly funds received</title><desc id="chart-description">Example data: April through July zero pesos, August 8,000 pesos, September zero pesos. Total 8,000 pesos.</desc><defs><linearGradient id="chart-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3c6ddc" stop-opacity=".22"/><stop offset="100%" stop-color="#3c6ddc" stop-opacity="0"/></linearGradient></defs>@foreach([40, 90, 140, 190] as $y)<line x1="65" y1="{{ $y }}" x2="810" y2="{{ $y }}" stroke="currentColor" opacity=".09"/>@endforeach<text x="0" y="44">₱8,000</text><text x="0" y="94">₱5,333</text><text x="0" y="144">₱2,667</text><text x="25" y="194">₱0</text><path d="M75 190 L220 190 L365 190 L510 190 L655 40 L800 190 L800 210 L75 210 Z" fill="url(#chart-fill)"/><path d="M75 190 L220 190 L365 190 L510 190 L655 40 L800 190" fill="none" stroke="#3c6ddc" stroke-width="3" stroke-linejoin="round"/>@foreach(['Apr','May','Jun','Jul','Aug','Sep'] as $i => $month)<circle cx="{{ 75 + $i * 145 }}" cy="{{ $i === 4 ? 40 : 190 }}" r="5" fill="#3c6ddc" stroke="white" stroke-width="2"/><text x="{{ 65 + $i * 145 }}" y="239">{{ $month }}</text>@endforeach</svg>
        <details><summary class="hope-text-button">View chart data</summary><div class="hope-table-wrap"><table class="hope-table"><caption class="sr-only">Monthly funds received in 2026</caption><thead><tr><th>Month</th><th>Received</th></tr></thead><tbody>@foreach(['April','May','June','July','August','September'] as $month)<tr><td>{{ $month }}</td><td>{{ $month === 'August' ? '₱8,000' : '₱0' }}</td></tr>@endforeach</tbody></table></div></details></section>
        <div class="hope-grid two hope-section"><section class="hope-card"><h2>Your request journey</h2><p>One request is awaiting review, two have been approved or completed, and two were denied.</p><div class="hope-actions"><a class="hope-button secondary" href="{{ route('request-status') }}">Explore request history →</a></div></section><section class="hope-card"><h2>Keep the hope going</h2><p>Find a community, support a cause, or start your next funding request.</p><div class="hope-actions"><a class="hope-button secondary" href="{{ route('groups') }}">Discover groups →</a></div></section></div>
    @endif
</div>
@endsection
