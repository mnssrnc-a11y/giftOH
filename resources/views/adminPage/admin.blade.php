@extends('layouts.admin')

@section('title', 'Admin Workspace - Gift of Hope')
@section('content')
@php
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
    $when = fn ($value) => $value ? \Carbon\Carbon::parse($value)->diffForHumans() : '—';
    $day = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('M d, Y') : '—';
    $admin = Auth::user();
    $categories = collect($fundingRequests)->pluck('category')->unique()->sort()->values();
    $granted = array_values(array_filter($fundingRequests, fn ($row) => $row['status'] === 'approved'));
    $completed = array_values(array_filter($fundingRequests, fn ($row) => $row['status'] === 'completed'));
    $postTypes = ['announcement' => 'Announcement', 'funding_update' => 'Funding update', 'compiled_report' => 'Compiled report'];
    $clientData = [
        // Land back on Settings after a profile photo upload (the redirect cannot carry a #hash).
        'initialPage' => session('profile_picture_status') || $errors->has('profile_picture') ? 'settings' : null,
        'donationSeries' => $donationSeries,
        'fundingSeries' => $fundingSeries,
        'availableFunds' => $availableFunds,
        'routes' => [
            'action' => route('admin.fund-request.action', ['id' => '__ID__']),
            'recommendation' => route('admin.fund-request.recommendation', ['id' => '__ID__']),
        ],
    ];
@endphp
<script>
    window.giftOfHopeFundingRequests = @json($fundingRequests);
    window.giftOfHopeAdminData = @json($clientData);
</script>
@if (session('status'))
    <div class="admin-notice" style="margin-bottom:18px">{{ session('status') }}</div>
@endif
@if (session('alert_error') || ($errors->any() && ! $errors->has('profile_picture')))
    <div class="admin-notice admin-notice-error" style="margin-bottom:18px">{{ session('alert_error') ?? $errors->first() }}</div>
@endif

<section class="admin-page is-active" data-admin-page="dashboard">
    <div class="admin-page-head">
        <div><h2>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ $admin->fname ?? 'Admin' }}!</h2><p>Here is what is happening across Gift of Hope today.</p></div>
        <div class="admin-actions">
            <button class="admin-button" data-admin-go="updates">✎ Post update</button>
            <button class="admin-button primary" data-admin-go="funding">Review requests</button>
        </div>
        <form class="logout-form" method="POST" action="/logout">
            @csrf
            <button class="admin-button danger" type="submit">Log out</button>
        </form>
    </div>
    <div class="admin-grid admin-stat-grid">
        <article class="admin-stat warn"><div class="admin-stat-top"><div class="admin-stat-icon">⌛</div><span class="admin-stat-change">{{ $fundingSummary['awaitingCount'] }} with super admin</span></div><h3>{{ $pendingCount }}</h3><p>Pending requests</p></article>
        <article class="admin-stat" data-admin-iot-monitor><div class="admin-stat-top"><div class="admin-stat-icon">▣</div><span class="admin-stat-change" data-admin-iot-online-label>{{ !is_null($onlineBoxCount) ? $onlineBoxCount . ' online' : 'Live status unavailable' }}</span></div><h3 data-admin-iot-total>{{ $smartBoxCount ?? '—' }}</h3><p>Smart boxes</p></article>
        <article class="admin-stat green"><div class="admin-stat-top"><div class="admin-stat-icon">↗</div><span class="admin-stat-change">{{ is_null($donationOverview) ? 'Unavailable' : 'Boxes + donations' }}</span></div><h3>{{ is_null($donationOverview) ? '—' : $peso($donationOverview) }}</h3><p>Donation overview</p></article>
        <article class="admin-stat purple"><div class="admin-stat-top"><div class="admin-stat-icon">▤</div><span class="admin-stat-change">{{ $fundingSummary['decidedCount'] }} decided</span></div><h3>{{ $totalFundRequests }}</h3><p>Total fund requests</p></article>
        <article class="admin-stat green"><div class="admin-stat-top"><div class="admin-stat-icon">₱</div><span class="admin-stat-change">{{ is_null($totalFunds) ? 'Unavailable' : 'Live total' }}</span></div><h3>{{ is_null($totalFunds) ? '—' : $peso($totalFunds) }}</h3><p>Total funds</p></article>
        <article class="admin-stat"><div class="admin-stat-top"><div class="admin-stat-icon">◉</div><span class="admin-stat-change">{{ is_null($availableFunds) ? 'Unavailable' : $availablePercentage . '% available' }}</span></div><h3>{{ is_null($availableFunds) ? '—' : $peso($availableFunds) }}</h3><p>Available funds</p></article>
    </div>

    <div class="admin-quick-actions" aria-label="Quick actions">
        <div class="admin-quick-copy"><strong>Quick actions</strong><span>Common admin tasks</span></div>
        <button data-admin-go="funding"><i>✓</i><span>Review pending</span></button>
        <button data-admin-go="updates"><i>✎</i><span>Post an update</span></button>
        <button data-admin-go="reports"><i>▥</i><span>Generate report</span></button>
        <a href="{{ route('iot-monitor') }}"><i>▣</i><span>Check IoT boxes</span></a>
    </div>

    <div class="admin-grid admin-two-col">
        <article class="admin-card">
            <div class="admin-card-head"><div><h3>Monthly donations</h3><p>Recorded donations, last 6 months</p></div><span class="admin-status in-progress">{{ $donationRecordCount }} records</span></div>
            <div class="admin-card-body admin-chart" data-chart="donations" aria-label="Monthly donations line chart"></div>
        </article>
        <article class="admin-card">
            <div class="admin-card-head"><div><h3>Funding trend</h3><p><span class="admin-legend" style="--c:#3b82f6">Requested</span> <span class="admin-legend" style="--c:#10b981">Granted</span></p></div><span class="admin-status in-progress">Last 6 months</span></div>
            <div class="admin-card-body admin-chart" data-chart="funding" aria-label="Requested versus granted funding chart"></div>
        </article>
    </div>

    <div class="admin-grid admin-two-col">
        <article class="admin-card">
            <div class="admin-card-head"><div><h3>Latest transactions</h3><p>Most recent recorded donations</p></div><a class="admin-button" href="{{ route('donations') }}">View ledger</a></div>
            @if (count($transactions))
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Transaction</th><th>Source</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead><tbody>
                    @foreach ($transactions as $transaction)
                        <tr><td><strong>{{ \Illuminate\Support\Str::limit($transaction['id'], 12) }}</strong><small>{{ $when($transaction['date']) }}</small></td><td>{{ $transaction['box'] ? 'Box ' . $transaction['box'] : ($transaction['donor'] ?? 'Direct') }}</td><td>{{ ucfirst($transaction['type']) }}</td><td><strong>{{ $peso($transaction['amount']) }}</strong></td><td><span class="admin-status {{ in_array($transaction['status'], ['verified', 'completed', 'paid'], true) ? 'approved' : '' }}">{{ $transaction['status'] }}</span></td></tr>
                    @endforeach
                </tbody></table></div>
            @else
                <div class="admin-empty admin-state-visible"><strong>No donation records yet</strong><p>Donations appear here as they are recorded in Firebase.</p></div>
            @endif
        </article>
        <article class="admin-card">
            <div class="admin-card-head"><div><h3>Fund allocation</h3><p>Granted funds by category</p></div><strong style="font-size:13px">{{ is_null($totalFunds) ? '—' : $peso($totalFunds) . ' total' }}</strong></div>
            <div class="admin-card-body admin-allocation-list">
                @forelse ($allocation as $row)
                    <div class="admin-allocation-row"><div><span><i style="background:{{ $row['color'] }}"></i>{{ $row['label'] }}</span><b>{{ $peso($row['amount']) }}</b></div><div class="admin-progress"><span style="width:{{ min(100, $row['percent']) }}%;background:{{ $row['color'] }}"></span></div><small>{{ $row['percent'] }}% of total funds</small></div>
                @empty
                    <div class="admin-empty admin-state-visible" style="padding:30px 10px"><strong>No funds granted yet</strong><p>Allocation appears once the super admin finalizes an approval.</p></div>
                @endforelse
            </div>
        </article>
    </div>

    <div class="admin-grid admin-two-col">
        <article class="admin-card"><div class="admin-card-head"><div><h3>Recent activities</h3><p>Latest requests, reviews, and updates</p></div><button class="admin-button" data-admin-go="funding">Open funding</button></div><div class="admin-card-body admin-list">
            @forelse ($activities as $activity)
                <div class="admin-list-row"><div class="admin-list-icon">{{ $activity['icon'] }}</div><div class="admin-list-copy"><strong>{{ $activity['title'] }}</strong><span>{{ $activity['detail'] }}</span></div><div class="admin-list-meta">{{ $when($activity['at']) }}@if ($activity['amount'])<br><b>{{ $peso($activity['amount']) }}</b>@endif</div></div>
            @empty
                <div class="admin-empty admin-state-visible" style="padding:30px 10px"><strong>No activity yet</strong></div>
            @endforelse
        </div></article>
        <article class="admin-card"><div class="admin-card-head"><div><h3>Latest IoT alerts</h3><p>Live from Firebase</p></div><a class="admin-button" href="{{ route('iot-monitor') }}">Open monitor</a></div><div class="admin-card-body admin-list" data-admin-iot-alerts>
            <div class="admin-loading admin-state-visible" style="padding:30px 10px"><div class="admin-spinner"></div>Connecting to smart boxes…</div>
        </div></article>
    </div>

    <article class="admin-card"><div class="admin-card-head"><div><h3>Smart box status</h3><p>Live telemetry from Firebase</p></div><span class="admin-status" data-admin-iot-summary>Connecting…</span></div><div class="admin-card-body admin-box-grid" data-admin-iot-boxes></div></article>
</section>

<section class="admin-page" data-admin-page="updates">
    <div class="admin-page-head"><div><h2>System updates</h2><p>Post announcements and compiled funding updates for users and the public landing page.</p></div></div>
    <div class="admin-grid admin-two-col admin-updates-grid">
        <article class="admin-card">
            <div class="admin-card-head"><div><h3>New update</h3><p>Write an announcement or compile the current funding requests</p></div></div>
            <form class="admin-card-body admin-post-form" method="POST" action="{{ route('admin.posts.store') }}" data-post-form>
                @csrf
                <div class="admin-field"><label for="post-type">Type</label><select id="post-type" class="admin-select" name="type" style="width:100%" data-post-type>@foreach ($postTypes as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="admin-field"><label for="post-audience">Who can see it</label><select id="post-audience" class="admin-select" name="audience" style="width:100%"><option value="public" @selected(old('audience', 'public') === 'public')>Everyone: landing page and user pages</option><option value="users" @selected(old('audience') === 'users')>Signed-in users only</option></select></div>
                <div class="admin-field"><label for="post-title">Title</label><input id="post-title" class="admin-input" name="title" maxlength="150" required value="{{ old('title') }}" data-post-title></div>
                <div class="admin-field"><label for="post-body">Message</label><textarea id="post-body" class="admin-textarea" name="body" rows="10" maxlength="10000" required data-post-body>{{ old('body') }}</textarea></div>
                <div class="admin-field admin-compile-options">
                    <label>Compile from charity home requests</label>
                    <div class="admin-actions">
                        <select class="admin-select" data-compile-scope>
                            <option value="all">All requests</option>
                            <option value="pending">Pending review</option>
                            <option value="awaiting">Awaiting super admin</option>
                            <option value="approved">Approved</option>
                            <option value="month">Submitted this month</option>
                        </select>
                        <button type="button" class="admin-button" data-compile-post>⟲ Generate compiled update</button>
                    </div>
                    <small>Only organization, category, amount, and status are included. Contact details are never compiled.</small>
                </div>
                <div class="admin-modal-actions" style="margin-top:6px"><button class="admin-button primary" type="submit">Publish update</button></div>
            </form>
        </article>
        <article class="admin-card">
            <div class="admin-card-head"><div><h3>Published updates</h3><p>{{ count($posts) }} {{ \Illuminate\Support\Str::plural('post', count($posts)) }}</p></div></div>
            <div class="admin-card-body admin-post-list">
                @forelse ($posts as $post)
                    <article class="admin-post">
                        <div class="admin-post-head"><div><strong>{{ $post['title'] }}</strong><small>{{ $post['author'] }} · {{ $when($post['date']) }}</small></div><div class="admin-actions"><span class="admin-status {{ $post['audience'] === 'public' ? 'approved' : 'cancelled' }}">{{ $post['audience'] === 'public' ? 'Public' : 'Users only' }}</span><span class="admin-status in-progress">{{ $postTypes[$post['type']] ?? 'Update' }}</span></div></div>
                        <p>{{ $post['body'] }}</p>
                        @if ($post['author_id'] === (string) $admin->getAuthIdentifier())
                            <form method="POST" action="{{ route('admin.posts.destroy', $post['id']) }}" data-delete-post>
                                @csrf @method('DELETE')
                                <button class="admin-button" type="submit">Delete</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <div class="admin-empty admin-state-visible"><strong>No updates posted yet</strong><p>Your first update will appear here, on user pages, and (if public) on the landing page.</p></div>
                @endforelse
            </div>
        </article>
    </div>
</section>

<section class="admin-page" data-admin-page="funding">
    <div class="admin-page-head"><div><h2>Funding management</h2><p>Review requests. Your approvals and rejections go to the super admin for finalization.</p></div><button class="admin-button primary" data-reload>↻ Refresh list</button></div>
    <div class="admin-grid admin-funding-summary">
        <article><span>Pending value</span><strong>{{ $peso($fundingSummary['pendingValue']) }}</strong><small>{{ $fundingSummary['pendingCount'] }} request(s) awaiting your review</small></article>
        <article><span>With super admin</span><strong>{{ $peso($fundingSummary['awaitingValue']) }}</strong><small>{{ $fundingSummary['awaitingCount'] }} request(s) awaiting finalization</small></article>
        <article><span>Granted this month</span><strong>{{ $peso($fundingSummary['grantedMonth']) }}</strong><small>{{ $fundingSummary['grantedMonthCount'] }} request(s) · {{ $peso($fundingSummary['grantedTotal']) }} all time</small></article>
        <article><span>Approval rate</span><strong>{{ is_null($fundingSummary['approvalRate']) ? '—' : $fundingSummary['approvalRate'] . '%' }}</strong><small>{{ $fundingSummary['decidedCount'] }} finalized decision(s)</small></article>
    </div>
    <div class="admin-tabs" data-funding-tabs>
        @foreach (['all' => 'All requests', 'pending' => 'Pending', 'awaiting' => 'Awaiting super admin', 'approved' => 'Approved', 'rejected' => 'Rejected', 'completed' => 'Completed'] as $key => $label)<button class="admin-tab {{ $key === 'all' ? 'is-active' : '' }}" data-status-tab="{{ $key }}">{{ $label }}</button>@endforeach
    </div>
    <div class="admin-card">
        <div class="admin-card-body">
            <div class="admin-toolbar">
                <input class="admin-input admin-search" type="search" placeholder="Search project, organization, or requester…" data-funding-search>
                <select class="admin-select" data-funding-category><option value="all">All categories</option>@foreach ($categories as $category)<option>{{ $category }}</option>@endforeach</select>
                <select class="admin-select" data-funding-sort><option value="newest">Newest first</option><option value="amount-desc">Amount: high to low</option><option value="amount-asc">Amount: low to high</option><option value="name">Project name</option></select>
            </div>
        </div>
        <div class="admin-empty" data-funding-empty><div style="font-size:30px">⌕</div><strong>No funding requests found</strong><p>Try changing your search or filter.</p></div>
        <div class="admin-table-wrap" data-funding-table-wrap>
            <table class="admin-table"><thead><tr><th>Request</th><th>Organization</th><th>Category</th><th>Amount</th><th>Status</th><th>Requested</th><th>Actions</th></tr></thead><tbody data-funding-body></tbody></table>
        </div>
        <div class="admin-pagination"><span data-funding-range></span><div class="admin-page-buttons"><button data-page-prev>‹</button><div data-page-numbers style="display:flex;gap:5px"></div><button data-page-next>›</button></div></div>
    </div>
</section>

<section class="admin-page" data-admin-page="reports">
    <div class="admin-page-head"><div><h2>Reports center</h2><p>Operational and funding performance from live records.</p></div><div class="admin-actions"><button class="admin-button" data-export-csv>▦ Export CSV</button><button class="admin-button primary" data-print>⌑ Print / PDF</button></div></div>
    <div class="admin-report-tabs">
        @foreach (['iot' => 'IoT reports', 'funding' => 'Funding reports', 'funded' => 'Funded projects', 'finished' => 'Finished projects'] as $key => $label)<button class="admin-report-tab {{ $key === 'iot' ? 'is-active' : '' }}" data-report-tab="{{ $key }}">{{ $label }}</button>@endforeach
    </div>
    <div class="admin-toolbar"><input class="admin-input admin-search" type="search" placeholder="Search current report…" data-report-search><label class="admin-field"><span>Start date</span><input class="admin-input admin-date" type="date" data-report-from></label><label class="admin-field"><span>End date</span><input class="admin-input admin-date" type="date" data-report-to></label><button class="admin-button" data-report-clear>Clear filters</button></div>

    <article class="admin-card admin-report-insight">
        <div class="admin-card-body"><div><span class="admin-eyebrow">Automated insight</span><h3>{{ $reportInsight['headline'] }}</h3><p>{{ $reportInsight['body'] }}</p></div><div class="admin-insight-metrics">@foreach ($reportInsight['metrics'] as $metric)<span><b>{{ $metric['value'] }}</b>{{ $metric['label'] }}</span>@endforeach</div></div>
    </article>

    <div class="admin-report-panel is-active" data-report-panel="iot">
        <div class="admin-grid admin-report-cards"><div class="admin-report-card"><span>Total boxes</span><strong data-report-iot="total">{{ $smartBoxCount ?? '—' }}</strong><small data-report-iot="online">Connecting…</small></div><div class="admin-report-card"><span>Collected by boxes</span><strong data-report-iot="collected">—</strong><small>Running total reported by boxes</small></div><div class="admin-report-card"><span>Coins counted</span><strong data-report-iot="coins">—</strong><small>Across all boxes</small></div><div class="admin-report-card"><span>Offline boxes</span><strong data-report-iot="offline">—</strong><small>Need attention</small></div></div>
        <article class="admin-card"><div class="admin-card-head"><div><h3>IoT device report</h3><p>Live collection and connectivity per box</p></div></div><div class="admin-table-wrap"><table class="admin-table" data-report-table><thead><tr><th>Serial</th><th>Location</th><th>Collected</th><th>Coins</th><th>Status</th><th>Last seen</th></tr></thead><tbody data-report-iot-body><tr><td colspan="6">Connecting to smart boxes…</td></tr></tbody></table></div></article>
    </div>
    <div class="admin-report-panel" data-report-panel="funding">
        <div class="admin-grid admin-report-cards"><div class="admin-report-card"><span>Total requested</span><strong>{{ $peso(array_sum(array_column($fundingRequests, 'amount'))) }}</strong><small>{{ $totalFundRequests }} request(s)</small></div><div class="admin-report-card"><span>Total granted</span><strong>{{ $peso($fundingSummary['grantedTotal']) }}</strong><small>{{ count($granted) + count($completed) }} request(s)</small></div><div class="admin-report-card"><span>Approval rate</span><strong>{{ is_null($fundingSummary['approvalRate']) ? '—' : $fundingSummary['approvalRate'] . '%' }}</strong><small>Of finalized requests</small></div><div class="admin-report-card"><span>Available funds</span><strong>{{ is_null($availableFunds) ? '—' : $peso($availableFunds) }}</strong><small>{{ is_null($availableFunds) ? 'Unavailable' : $availablePercentage . '% of total funds' }}</small></div></div>
        <article class="admin-card"><div class="admin-card-head"><div><h3>Funding report</h3><p>Monthly request and grant summary</p></div></div><div class="admin-table-wrap"><table class="admin-table" data-report-table><thead><tr><th>Month</th><th>Requests</th><th>Requested</th><th>Granted</th><th>Rejected</th><th>Rate</th></tr></thead><tbody>
            @forelse ($fundingReport as $row)
                <tr data-date="{{ $row['date'] }}"><td>{{ $row['month'] }}</td><td>{{ $row['requests'] }}</td><td>{{ $peso($row['requested']) }}</td><td>{{ $peso($row['granted']) }}</td><td>{{ $row['rejected'] }}</td><td>{{ $row['rate'] }}</td></tr>
            @empty
                <tr data-empty><td colspan="6">No funding requests recorded yet.</td></tr>
            @endforelse
        </tbody></table></div></article>
    </div>
    <div class="admin-report-panel" data-report-panel="funded">
        <div class="admin-grid admin-report-cards"><div class="admin-report-card"><span>Active projects</span><strong>{{ count($granted) }}</strong><small>Across {{ collect($granted)->pluck('category')->unique()->count() }} categories</small></div><div class="admin-report-card"><span>Funds granted</span><strong>{{ $peso(array_sum(array_column($granted, 'granted'))) }}</strong><small>To active projects</small></div><div class="admin-report-card"><span>Awaiting finalization</span><strong>{{ $fundingSummary['awaitingCount'] }}</strong><small>With the super admin</small></div><div class="admin-report-card"><span>Average grant</span><strong>{{ count($granted) ? $peso(array_sum(array_column($granted, 'granted')) / count($granted)) : '—' }}</strong><small>Per active project</small></div></div>
        <article class="admin-card"><div class="admin-card-head"><div><h3>Funded projects</h3><p>Approved requests currently in progress</p></div></div><div class="admin-table-wrap"><table class="admin-table" data-report-table><thead><tr><th>Project</th><th>Organization</th><th>Category</th><th>Requested</th><th>Granted</th><th>Approved</th></tr></thead><tbody>
            @forelse ($granted as $row)
                <tr data-date="{{ $row['decided_at'] }}"><td>{{ $row['project'] }}</td><td>{{ $row['organization'] }}</td><td>{{ $row['category'] }}</td><td>{{ $peso($row['amount']) }}</td><td>{{ $peso($row['granted']) }}</td><td>{{ $day($row['decided_at']) }}</td></tr>
            @empty
                <tr data-empty><td colspan="6">No approved projects yet.</td></tr>
            @endforelse
        </tbody></table></div></article>
    </div>
    <div class="admin-report-panel" data-report-panel="finished">
        <div class="admin-grid admin-report-cards"><div class="admin-report-card"><span>Finished projects</span><strong>{{ count($completed) }}</strong><small>Marked completed</small></div><div class="admin-report-card"><span>Total invested</span><strong>{{ $peso(array_sum(array_column($completed, 'granted'))) }}</strong><small>In finished projects</small></div><div class="admin-report-card"><span>Organizations</span><strong>{{ collect($completed)->pluck('organization')->unique()->count() }}</strong><small>Served to completion</small></div><div class="admin-report-card"><span>Categories</span><strong>{{ collect($completed)->pluck('category')->unique()->count() }}</strong><small>Represented</small></div></div>
        <article class="admin-card"><div class="admin-card-head"><div><h3>Finished projects</h3><p>Completed work and final amounts</p></div></div><div class="admin-table-wrap"><table class="admin-table" data-report-table><thead><tr><th>Project</th><th>Organization</th><th>Category</th><th>Amount</th><th>Approved</th><th>Status</th></tr></thead><tbody>
            @forelse ($completed as $row)
                <tr data-date="{{ $row['decided_at'] }}"><td>{{ $row['project'] }}</td><td>{{ $row['organization'] }}</td><td>{{ $row['category'] }}</td><td>{{ $peso($row['granted']) }}</td><td>{{ $day($row['decided_at']) }}</td><td><span class="admin-status completed">Completed</span></td></tr>
            @empty
                <tr data-empty><td colspan="6">No finished projects yet.</td></tr>
            @endforelse
        </tbody></table></div></article>
    </div>
</section>

<section class="admin-page" data-admin-page="settings">
    <div class="admin-page-head"><div><h2>Settings</h2><p>Your account details, preferences, and security.</p></div></div>
    <div class="admin-grid admin-settings-grid">
        <aside class="admin-card admin-settings-nav"><button class="is-active" data-settings-tab="information">◉ My information</button><button data-settings-tab="profile">♙ Profile photo</button><button data-settings-tab="preferences">◐ Preferences</button><button data-settings-tab="security">◇ Security</button></aside>
        <div class="admin-card"><div class="admin-card-body">
            <div class="admin-settings-panel is-active" data-settings-panel="information"><div class="admin-card-head" style="padding:0 0 18px;margin-bottom:20px"><div><h3>My information</h3><p>As stored on your Gift of Hope account</p></div></div><div class="admin-form-grid">
                <div class="admin-field"><label>Full name</label><input value="{{ trim(($admin->fname ?? '') . ' ' . ($admin->mname ?? '') . ' ' . ($admin->lname ?? '')) ?: ($admin->name ?? '—') }}" readonly></div>
                <div class="admin-field"><label>Role</label><input value="Administrator" readonly></div>
                <div class="admin-field"><label>Email</label><input value="{{ $admin->email ?? '—' }}" readonly></div>
                <div class="admin-field"><label>Phone</label><input value="{{ $admin->phone ?? '—' }}" readonly></div>
                <div class="admin-field"><label>Birthday</label><input value="{{ $day($admin->date_of_birth ?? null) }}" readonly></div>
                <div class="admin-field"><label>Member since</label><input value="{{ $day($admin->created_at ?? null) }}" readonly></div>
            </div></div>
            <div class="admin-settings-panel" data-settings-panel="profile"><div class="admin-card-head" style="padding:0 0 18px;margin-bottom:20px"><div><h3>Profile photo</h3><p>JPG or PNG, up to 5 MB</p></div></div>
                <form class="admin-profile-hero" method="POST" action="{{ route('settings.profile-picture') }}" enctype="multipart/form-data" data-profile-form>
                    @csrf
                    @php($photo = $admin->profilePhotoUrl())
                    <div class="admin-profile-photo" data-profile-photo @if ($photo) style="background:center/cover url('{{ $photo }}')" @endif><span data-profile-initial @if ($photo) hidden @endif>{{ strtoupper(substr($admin->fname ?? 'A', 0, 1)) }}</span><button type="button" data-profile-upload aria-label="Choose photo">＋</button></div>
                    <div><strong>{{ trim(($admin->fname ?? '') . ' ' . ($admin->lname ?? '')) }}</strong><p style="margin:5px 0;color:var(--admin-muted);font-size:12px">Administrator · Gift of Hope</p><div class="admin-actions"><button class="admin-button" type="button" data-profile-upload>{{ $photo ? 'Change photo' : 'Upload photo' }}</button><button class="admin-button primary" type="submit" data-profile-save hidden>Save photo</button></div><input type="file" name="profile_picture" accept="image/png,image/jpeg" data-profile-input hidden></div>
                </form>
                @if (session('profile_picture_status'))<div class="admin-notice">{{ session('profile_picture_status') }}</div>@endif
                @error('profile_picture')<div class="admin-notice admin-notice-error">{{ $message }}</div>@enderror
                <p class="admin-field-hint" data-profile-hint>Pick a photo to preview it, then save.</p>
            </div>
            <div class="admin-settings-panel" data-settings-panel="preferences"><div class="admin-card-head" style="padding:0 0 18px"><div><h3>Preferences</h3><p>Saved in this browser</p></div></div><div class="admin-setting-row"><div><strong>Dark mode</strong><span>Use a darker color theme throughout the admin workspace</span></div><label class="admin-switch"><input type="checkbox" data-dark-switch><i></i></label></div><div class="admin-setting-row"><div><strong>Compact tables</strong><span>Reduce table row height to show more records</span></div><label class="admin-switch"><input type="checkbox" data-compact-switch><i></i></label></div></div>
            <div class="admin-settings-panel" data-settings-panel="security"><div class="admin-card-head" style="padding:0 0 18px;margin-bottom:16px"><div><h3>Security</h3><p>Sign-in and approval verification</p></div></div>
                <div class="admin-security-item"><div>@</div><div><strong>Email address</strong><span>{{ $admin->email ?? '—' }}</span></div></div>
                <div class="admin-security-item"><div>☎</div><div><strong>Phone number</strong><span>{{ $admin->phone ?? 'Not set' }}</span></div></div>
                @php($twoFactor = filter_var($admin->email_notifications ?? true, FILTER_VALIDATE_BOOLEAN))
                <form class="admin-setting-row" method="POST" action="{{ route('settings.update') }}" data-two-factor-form>
                    @csrf
                    <input type="hidden" name="dark_mode" value="{{ filter_var($admin->dark_mode ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0 }}">
                    <input type="hidden" name="email_notifications" value="0">
                    <div><strong>Email verification (2FA)</strong><span>Require an emailed code at sign-in and before each funding decision is submitted</span></div>
                    <label class="admin-switch"><input type="checkbox" name="email_notifications" value="1" @checked($twoFactor) data-two-factor-switch><i></i></label>
                </form>
            </div>
        </div></div>
    </div>
</section>
@endsection
