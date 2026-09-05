@extends('layouts.admin')

@section('title', 'Admin Workspace - Gift of Hope')

@section('content')
@if (session('status'))
    <div class="admin-notice" style="margin-bottom:18px">{{ session('status') }}</div>
@endif
@if (session('alert_error'))
    <div class="admin-notice" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b">{{ session('alert_error') }}</div>
@endif

<section class="admin-page is-active" data-admin-page="dashboard">
    <div class="admin-page-head">
        <div><h2>Good morning, {{ Auth::user()->fname ?? 'Admin' }}!</h2><p>Here is what is happening across Gift of Hope today.</p></div>
        <div class="admin-actions">
            <button class="admin-button" data-dashboard-state="loading">Preview loading</button>
            <button class="admin-button" data-dashboard-state="error">Preview error</button>
            <button class="admin-button primary" data-admin-go="funding">Review requests</button>
        </div>
        <form class="logout-form" method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="admin-button danger" type="submit">Log out</button>
        </form>
    </div>
    <div class="admin-loading" data-dashboard-loading><div class="admin-spinner"></div><strong>Loading dashboard data…</strong></div>
    <div class="admin-error" data-dashboard-error><div style="font-size:28px;margin-bottom:8px">!</div><strong>Dashboard data could not be loaded.</strong><p>This is a UI preview. Try again to restore the mock data.</p><button class="admin-button primary" data-dashboard-state="ready">Try again</button></div>
    <div data-dashboard-content>
        <div class="admin-grid admin-stat-grid">
            <article class="admin-stat warn"><div class="admin-stat-top"><div class="admin-stat-icon">⌛</div><span class="admin-stat-change">Needs review</span></div><h3>{{ $pendingRequests->count() ?: 4 }}</h3><p>Pending requests</p></article>
            <article class="admin-stat"><div class="admin-stat-top"><div class="admin-stat-icon">▣</div><span class="admin-stat-change">+2 this month</span></div><h3>18</h3><p>Smart boxes</p></article>
            <article class="admin-stat green"><div class="admin-stat-top"><div class="admin-stat-icon">↗</div><span class="admin-stat-change">+12.5%</span></div><h3>₱124.6K</h3><p>Donation overview</p></article>
            <article class="admin-stat purple"><div class="admin-stat-top"><div class="admin-stat-icon">▤</div><span class="admin-stat-change">36 total</span></div><h3>12</h3><p>Request fund count</p></article>
            <article class="admin-stat green"><div class="admin-stat-top"><div class="admin-stat-icon">₱</div><span class="admin-stat-change">+8.2%</span></div><h3>₱2.48M</h3><p>Total funds</p></article>
            <article class="admin-stat"><div class="admin-stat-top"><div class="admin-stat-icon">◉</div><span class="admin-stat-change">62% available</span></div><h3>₱1.54M</h3><p>Available funds</p></article>
        </div>

        <div class="admin-quick-actions" aria-label="Quick actions">
            <div class="admin-quick-copy"><strong>Quick actions</strong><span>Common admin tasks</span></div>
            <button data-admin-go="funding"><i>✓</i><span>Review pending</span></button>
            <button data-admin-go="reports"><i>▥</i><span>Generate report</span></button>
            <a href="{{ route('iot-monitor') }}"><i>▣</i><span>Check IoT boxes</span></a>
            <button data-admin-go="settings"><i>⚙</i><span>Update profile</span></button>
        </div>

        <div class="admin-grid admin-two-col">
            <article class="admin-card">
                <div class="admin-card-head"><div><h3>Monthly donations</h3><p>Donations received from smart boxes</p></div><select class="admin-select"><option>Last 6 months</option><option>This year</option></select></div>
                <div class="admin-card-body admin-chart">
                    <svg viewBox="0 0 700 250" preserveAspectRatio="none" role="img" aria-label="Monthly donations line chart">
                        <defs><linearGradient id="adminGradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#60a5fa"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs>
                        <path class="grid" d="M45 30H680M45 90H680M45 150H680M45 210H680"/><path class="area" d="M45 185L165 165L285 139L405 120L525 84L680 48L680 210L45 210Z"/><polyline class="line" points="45,185 165,165 285,139 405,120 525,84 680,48"/>
                        <g><circle cx="45" cy="185" r="5"/><circle cx="165" cy="165" r="5"/><circle cx="285" cy="139" r="5"/><circle cx="405" cy="120" r="5"/><circle cx="525" cy="84" r="5"/><circle cx="680" cy="48" r="5"/></g>
                        <g text-anchor="middle"><text x="45" y="235">Feb</text><text x="165" y="235">Mar</text><text x="285" y="235">Apr</text><text x="405" y="235">May</text><text x="525" y="235">Jun</text><text x="680" y="235">Jul</text></g>
                    </svg>
                </div>
            </article>
            <article class="admin-card">
                <div class="admin-card-head"><div><h3>Funding trend</h3><p>Approved versus released funds</p></div><span class="admin-status approved">Healthy</span></div>
                <div class="admin-card-body admin-chart">
                    <svg viewBox="0 0 520 250" preserveAspectRatio="none" role="img" aria-label="Funding trend chart">
                        <path class="grid" d="M35 30H505M35 90H505M35 150H505M35 210H505"/><polyline class="line" points="35,184 120,159 205,165 290,110 375,89 505,53"/><polyline class="line alt" points="35,198 120,181 205,174 290,146 375,111 505,84"/>
                        <g text-anchor="middle"><text x="35" y="235">Feb</text><text x="120" y="235">Mar</text><text x="205" y="235">Apr</text><text x="290" y="235">May</text><text x="375" y="235">Jun</text><text x="505" y="235">Jul</text></g>
                    </svg>
                </div>
            </article>
        </div>

        <div class="admin-grid admin-two-col">
            <article class="admin-card">
                <div class="admin-card-head"><div><h3>Latest transactions</h3><p>Most recent smart-box collections</p></div><button class="admin-button" data-snackbar="Transaction list is up to date">View ledger</button></div>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Transaction</th><th>Smart box</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead><tbody>
                    <tr><td><strong>TXN-8072</strong><small>Today, 10:42 AM</small></td><td>SB-014 · Quezon City</td><td>Bills</td><td><strong>₱12,480</strong></td><td><span class="admin-status approved">Verified</span></td></tr>
                    <tr><td><strong>TXN-8071</strong><small>Today, 9:18 AM</small></td><td>SB-008 · Makati</td><td>Coins</td><td><strong>₱3,260</strong></td><td><span class="admin-status approved">Verified</span></td></tr>
                    <tr><td><strong>TXN-8070</strong><small>Yesterday, 5:34 PM</small></td><td>SB-001 · Makati</td><td>Mixed</td><td><strong>₱8,940</strong></td><td><span class="admin-status">Review</span></td></tr>
                    <tr><td><strong>TXN-8069</strong><small>Yesterday, 3:06 PM</small></td><td>SB-006 · Taguig</td><td>Bills</td><td><strong>₱16,720</strong></td><td><span class="admin-status approved">Verified</span></td></tr>
                </tbody></table></div>
            </article>
            <article class="admin-card">
                <div class="admin-card-head"><div><h3>Fund allocation</h3><p>How available funds are currently planned</p></div><strong style="font-size:13px">₱2.48M total</strong></div>
                <div class="admin-card-body admin-allocation-list">
                    @foreach ([['Education',36,'#3b82f6','₱892,800'],['Healthcare',28,'#10b981','₱694,400'],['Food & Shelter',22,'#f59e0b','₱545,600'],['Community',14,'#8b5cf6','₱347,200']] as $allocation)
                    <div class="admin-allocation-row"><div><span><i style="background:{{ $allocation[2] }}"></i>{{ $allocation[0] }}</span><b>{{ $allocation[3] }}</b></div><div class="admin-progress"><span style="width:{{ $allocation[1] }}%;background:{{ $allocation[2] }}"></span></div><small>{{ $allocation[1] }}% of total funds</small></div>
                    @endforeach
                </div>
            </article>
        </div>

        <div class="admin-grid admin-two-col">
            <article class="admin-card"><div class="admin-card-head"><div><h3>Recent activities</h3><p>Latest updates across the platform</p></div><button class="admin-button" data-snackbar="Activity log is up to date">View all</button></div><div class="admin-card-body admin-list">
                <div class="admin-list-row"><div class="admin-list-icon">✓</div><div class="admin-list-copy"><strong>Funding request approved</strong><span>Community Learning Hub · Maria Santos</span></div><div class="admin-list-meta">10 min ago<br><b>₱85,000</b></div></div>
                <div class="admin-list-row"><div class="admin-list-icon">₱</div><div class="admin-list-copy"><strong>Donation collection recorded</strong><span>Box SB-014 · Quezon City Hall</span></div><div class="admin-list-meta">42 min ago<br><b>₱12,480</b></div></div>
                <div class="admin-list-row"><div class="admin-list-icon">▣</div><div class="admin-list-copy"><strong>Smart box returned online</strong><span>Box SB-008 · Makati Central</span></div><div class="admin-list-meta">1 hr ago<br><span class="admin-status online">Online</span></div></div>
                <div class="admin-list-row"><div class="admin-list-icon">＋</div><div class="admin-list-copy"><strong>New request received</strong><span>Nutrition Support Program · HopeWorks</span></div><div class="admin-list-meta">2 hrs ago<br><span class="admin-status">Pending</span></div></div>
            </div></article>
            <article class="admin-card"><div class="admin-card-head"><div><h3>Latest IoT alerts</h3><p>Items needing attention</p></div><a class="admin-button" href="{{ route('iot-monitor') }}">Open monitor</a></div><div class="admin-card-body admin-list">
                <div class="admin-list-row"><div class="admin-list-icon" style="color:#dc2626">!</div><div class="admin-list-copy"><strong>Container almost full</strong><span>SB-003 · Manila North</span></div><div class="admin-list-meta"><span class="admin-status critical">Critical</span></div></div>
                <div class="admin-list-row"><div class="admin-list-icon" style="color:#d97706">⌁</div><div class="admin-list-copy"><strong>Intermittent connectivity</strong><span>SB-011 · Pasig Center</span></div><div class="admin-list-meta"><span class="admin-status">Warning</span></div></div>
                <div class="admin-list-row"><div class="admin-list-icon">▤</div><div class="admin-list-copy"><strong>Storage at 82%</strong><span>SB-006 · Taguig Hub</span></div><div class="admin-list-meta"><span class="admin-status maintenance">Monitor</span></div></div>
            </div></article>
        </div>

        <article class="admin-card"><div class="admin-card-head"><div><h3>Smart box status</h3><p>Live-style mock telemetry overview</p></div><span class="admin-status online">15 of 18 online</span></div><div class="admin-card-body admin-box-grid">
            @foreach ([['SB-001','Makati Central',84,'Online'],['SB-003','Manila North',96,'Critical'],['SB-008','Quezon City Hall',42,'Online'],['SB-011','Pasig Center',67,'Maintenance']] as $box)
            <div class="admin-box"><div class="admin-box-head"><div><strong>{{ $box[0] }}</strong><br><small>{{ $box[1] }}</small></div><span class="admin-status {{ strtolower($box[3]) }}">{{ $box[3] }}</span></div><div class="admin-progress"><span style="width:{{ $box[2] }}%;{{ $box[2] > 90 ? 'background:#dc2626' : '' }}"></span></div><p><span>Container level</span><b>{{ $box[2] }}%</b></p></div>
            @endforeach
        </div></article>
    </div>
</section>

<section class="admin-page" data-admin-page="funding">
    <div class="admin-page-head"><div><h2>Funding management</h2><p>Review, track, and update every funding request.</p></div><button class="admin-button primary" data-snackbar="Funding list refreshed">↻ Refresh list</button></div>
    <div class="admin-grid admin-funding-summary">
        <article><span>Pending value</span><strong>₱177,500</strong><small>3 mock requests awaiting review</small></article>
        <article><span>Approved this month</span><strong>₱443,000</strong><small>5 requests · 68% approval rate</small></article>
        <article><span>Released funds</span><strong>₱372,000</strong><small>84% of approved funding</small></article>
        <article><span>Average decision time</span><strong>2.4 days</strong><small>0.6 days faster than June</small></article>
    </div>
    <div class="admin-tabs" data-funding-tabs>
        @foreach (['all'=>'All requests','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled','completed'=>'Completed'] as $key=>$label)<button class="admin-tab {{ $key==='all'?'is-active':'' }}" data-status-tab="{{ $key }}">{{ $label }}</button>@endforeach
    </div>
    <div class="admin-card">
        <div class="admin-card-body">
            <div class="admin-toolbar">
                <input class="admin-input admin-search" type="search" placeholder="Search project, organization, or requester…" data-funding-search>
                <select class="admin-select" data-funding-category><option value="all">All categories</option><option>Education</option><option>Healthcare</option><option>Food & Shelter</option><option>Community</option></select>
                <select class="admin-select" data-funding-sort><option value="newest">Newest first</option><option value="amount-desc">Amount: high to low</option><option value="amount-asc">Amount: low to high</option><option value="name">Project name</option></select>
            </div>
        </div>
        <div class="admin-loading" data-funding-loading><div class="admin-spinner"></div>Loading requests…</div>
        <div class="admin-empty" data-funding-empty><div style="font-size:30px">⌕</div><strong>No funding requests found</strong><p>Try changing your search or filter.</p></div>
        <div class="admin-table-wrap" data-funding-table-wrap>
            <table class="admin-table"><thead><tr><th>Request</th><th>Organization</th><th>Category</th><th data-sort="amount">Amount ↕</th><th>Status</th><th>Requested</th><th>Actions</th></tr></thead><tbody data-funding-body></tbody></table>
        </div>
        <div class="admin-pagination"><span data-funding-range>Showing 1–5 of 10</span><div class="admin-page-buttons"><button data-page-prev>‹</button><div data-page-numbers style="display:flex;gap:5px"></div><button data-page-next>›</button></div></div>
    </div>

    <div class="admin-card admin-live-queue">
        <div class="admin-card-head"><div><h3>Existing approval queue</h3><p>The project’s working OTP-backed approval forms are preserved here.</p></div><span class="admin-status">{{ $pendingRequests->count() }} pending</span></div>
        <div class="admin-card-body">
            @forelse ($pendingRequests as $request)
                <article class="admin-live-request">
                    <div class="admin-live-request-head"><div><h4>{{ $request->title }}</h4><p>{{ $request->user->name ?? trim(($request->user->fname ?? '').' '.($request->user->lname ?? '')) }} · {{ $request->category->category_name ?? 'General' }}</p></div><strong>₱{{ number_format($request->amount_requested, 2) }}</strong></div>
                    <p>{{ $request->description }}</p>
                    <form method="POST" action="{{ route('admin.fund-request.action', $request->id) }}" data-confirm-form>
                        @csrf
                        <input name="notes" class="admin-input" type="text" placeholder="Decision notes (optional)">
                        <button type="submit" name="action" value="rejected" class="admin-button danger">Reject</button>
                        <button type="submit" name="action" value="approved" class="admin-button success">Approve</button>
                    </form>
                </article>
            @empty
                <div class="admin-empty admin-state-visible"><div style="font-size:32px">✓</div><strong>No live requests waiting for approval</strong><p>The mock list above remains available for UI testing.</p></div>
            @endforelse
        </div>
    </div>
</section>

<section class="admin-page" data-admin-page="reports">
    <div class="admin-page-head"><div><h2>Reports center</h2><p>Review operational and funding performance in one place.</p></div><div class="admin-actions"><button class="admin-button" data-export="pdf">▧ Export PDF</button><button class="admin-button" data-export="excel">▦ Export Excel</button><button class="admin-button primary" data-print>⌑ Print</button></div></div>
    <div class="admin-report-tabs">
        @foreach (['iot'=>'IoT reports','funding'=>'Funding reports','funded'=>'Funded projects','finished'=>'Finished projects'] as $key=>$label)<button class="admin-report-tab {{ $key==='iot'?'is-active':'' }}" data-report-tab="{{ $key }}">{{ $label }}</button>@endforeach
    </div>
    <div class="admin-toolbar"><input class="admin-input admin-search" type="search" placeholder="Search current report…" data-report-search><label class="admin-field"><span>Start date</span><input class="admin-input admin-date" type="date" value="2026-01-01"></label><label class="admin-field"><span>End date</span><input class="admin-input admin-date" type="date" value="2026-07-31"></label><select class="admin-select"><option>All statuses</option><option>Healthy</option><option>Needs attention</option></select><button class="admin-button primary" data-snackbar="Report filters applied">Apply filters</button></div>

    <article class="admin-card admin-report-insight">
        <div class="admin-card-body"><div><span class="admin-eyebrow">Automated insight</span><h3>Donation performance remains above the six-month average</h3><p>July donations are tracking 12.5% higher, while funding utilization remains within the planned range. One critical smart-box alert may affect the next collection cycle.</p></div><div class="admin-insight-metrics"><span><b>+12.5%</b>Donations</span><span><b>66.7%</b>Approval rate</span><span><b>1</b>Critical alert</span></div></div>
    </article>

    <div class="admin-report-panel is-active" data-report-panel="iot">
        <div class="admin-grid admin-report-cards"><div class="admin-report-card"><span>Total boxes</span><strong>18</strong><small>15 currently online</small></div><div class="admin-report-card"><span>Healthy devices</span><strong>83%</strong><small>+4% this month</small></div><div class="admin-report-card"><span>Memory usage</span><strong>64%</strong><small>Within normal range</small></div><div class="admin-report-card"><span>Open alerts</span><strong>3</strong><small style="color:#dc2626">1 requires action</small></div></div>
        <article class="admin-card"><div class="admin-card-head"><div><h3>IoT device report</h3><p>Container, health, storage, and alert history</p></div></div><div class="admin-table-wrap"><table class="admin-table" data-report-table><thead><tr><th>Serial</th><th>Location</th><th>Container</th><th>Health</th><th>Storage</th><th>Last activity</th></tr></thead><tbody><tr><td><strong>SB-001</strong></td><td>Makati Central</td><td>84%</td><td><span class="admin-status healthy">Healthy</span></td><td>62%</td><td>2 min ago</td></tr><tr><td><strong>SB-003</strong></td><td>Manila North</td><td>96%</td><td><span class="admin-status critical">Critical</span></td><td>78%</td><td>8 min ago</td></tr><tr><td><strong>SB-008</strong></td><td>Quezon City Hall</td><td>42%</td><td><span class="admin-status healthy">Healthy</span></td><td>51%</td><td>12 min ago</td></tr><tr><td><strong>SB-011</strong></td><td>Pasig Center</td><td>67%</td><td><span class="admin-status maintenance">Maintenance</span></td><td>82%</td><td>28 min ago</td></tr></tbody></table></div></article>
    </div>
    <div class="admin-report-panel" data-report-panel="funding">
        <div class="admin-grid admin-report-cards"><div class="admin-report-card"><span>Total requested</span><strong>₱1.28M</strong><small>36 requests</small></div><div class="admin-report-card"><span>Total approved</span><strong>₱864K</strong><small>24 requests</small></div><div class="admin-report-card"><span>Approval rate</span><strong>66.7%</strong><small>+6.1% this quarter</small></div><div class="admin-report-card"><span>Available funds</span><strong>₱1.54M</strong><small>62% of total funds</small></div></div>
        <article class="admin-card"><div class="admin-card-head"><div><h3>Funding report</h3><p>Monthly request and release summary</p></div></div><div class="admin-table-wrap"><table class="admin-table" data-report-table><thead><tr><th>Month</th><th>Requests</th><th>Requested</th><th>Approved</th><th>Released</th><th>Rate</th></tr></thead><tbody>@foreach ([['January',5,'₱180,000','₱135,000','₱120,000','75%'],['February',7,'₱264,000','₱198,000','₱172,000','75%'],['March',6,'₱215,000','₱142,000','₱130,000','66%'],['April',8,'₱322,000','₱201,000','₱186,000','62%']] as $row)<tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table></div></article>
    </div>
    <div class="admin-report-panel" data-report-panel="funded">
        <div class="admin-grid admin-report-cards"><div class="admin-report-card"><span>Active projects</span><strong>14</strong><small>Across 6 categories</small></div><div class="admin-report-card"><span>Funds released</span><strong>₱642K</strong><small>74% utilized</small></div><div class="admin-report-card"><span>People reached</span><strong>3,840</strong><small>+520 this month</small></div><div class="admin-report-card"><span>On schedule</span><strong>12</strong><small>2 need updates</small></div></div>
        <article class="admin-card"><div class="admin-card-head"><div><h3>Funded projects</h3><p>Active initiatives and utilization</p></div></div><div class="admin-table-wrap"><table class="admin-table" data-report-table><thead><tr><th>Project</th><th>Organization</th><th>Funded</th><th>Released</th><th>Progress</th><th>Status</th></tr></thead><tbody><tr><td>Community Learning Hub</td><td>Bayanihan Foundation</td><td>₱85,000</td><td>₱70,000</td><td>68%</td><td><span class="admin-status in-progress">In progress</span></td></tr><tr><td>Rural Medical Mission</td><td>CareBridge PH</td><td>₱120,000</td><td>₱120,000</td><td>84%</td><td><span class="admin-status in-progress">In progress</span></td></tr><tr><td>Nutrition Support</td><td>HopeWorks</td><td>₱48,500</td><td>₱32,000</td><td>51%</td><td><span class="admin-status in-progress">In progress</span></td></tr></tbody></table></div></article>
    </div>
    <div class="admin-report-panel" data-report-panel="finished">
        <div class="admin-grid admin-report-cards"><div class="admin-report-card"><span>Finished projects</span><strong>10</strong><small>4 this quarter</small></div><div class="admin-report-card"><span>Total invested</span><strong>₱718K</strong><small>98% utilized</small></div><div class="admin-report-card"><span>Beneficiaries</span><strong>7,240</strong><small>Verified outcomes</small></div><div class="admin-report-card"><span>Reports filed</span><strong>100%</strong><small>All complete</small></div></div>
        <article class="admin-card"><div class="admin-card-head"><div><h3>Finished projects</h3><p>Completed work and final outcomes</p></div></div><div class="admin-table-wrap"><table class="admin-table" data-report-table><thead><tr><th>Project</th><th>Organization</th><th>Completed</th><th>Amount</th><th>Beneficiaries</th><th>Status</th></tr></thead><tbody><tr><td>School Kit Drive</td><td>Bright Futures</td><td>Jul 12, 2026</td><td>₱65,000</td><td>820</td><td><span class="admin-status completed">Completed</span></td></tr><tr><td>Clean Water Access</td><td>Water for All</td><td>Jun 28, 2026</td><td>₱140,000</td><td>1,450</td><td><span class="admin-status completed">Completed</span></td></tr><tr><td>Emergency Food Packs</td><td>HopeWorks</td><td>May 19, 2026</td><td>₱92,000</td><td>1,100</td><td><span class="admin-status completed">Completed</span></td></tr></tbody></table></div></article>
    </div>
</section>

<section class="admin-page" data-admin-page="settings">
    <div class="admin-page-head"><div><h2>Settings</h2><p>Manage your profile, preferences, and security UI.</p></div><button class="admin-button primary" data-save-settings>Save changes</button></div>
    <div class="admin-grid admin-settings-grid">
        <aside class="admin-card admin-settings-nav"><button class="is-active" data-settings-tab="information">◉ My information</button><button data-settings-tab="profile">♙ My profile</button><button data-settings-tab="preferences">◐ Preferences</button><button data-settings-tab="security">◇ Security</button></aside>
        <div class="admin-card"><div class="admin-card-body">
            <div class="admin-settings-panel is-active" data-settings-panel="information"><div class="admin-card-head" style="padding:0 0 18px;margin-bottom:20px"><div><h3>My information</h3><p>Basic information shown across the admin workspace</p></div></div><div class="admin-form-grid"><div class="admin-field"><label>Full name</label><input value="{{ Auth::user()->name ?? trim((Auth::user()->fname ?? 'Admin').' '.(Auth::user()->lname ?? 'User')) }}"></div><div class="admin-field"><label>Birthday</label><input type="date" value="1990-08-15"></div><div class="admin-field"><label>Date started</label><input type="date" value="2024-01-08"></div><div class="admin-field"><label>Role</label><input value="Administrator" disabled></div><div class="admin-field"><label>Department</label><input value="Programs and Operations"></div><div class="admin-field"><label>Time zone</label><select class="admin-select" style="width:100%"><option>Asia/Manila (UTC+8)</option><option>UTC</option></select></div></div></div>
            <div class="admin-settings-panel" data-settings-panel="profile"><div class="admin-card-head" style="padding:0 0 18px;margin-bottom:20px"><div><h3>My profile</h3><p>Update your profile picture and public details</p></div></div><div class="admin-profile-hero"><div class="admin-profile-photo">{{ strtoupper(substr(Auth::user()->fname ?? 'A',0,1)) }}<button data-profile-upload>＋</button></div><div><strong>{{ Auth::user()->name ?? 'Admin User' }}</strong><p style="margin:5px 0;color:var(--admin-muted);font-size:12px">Administrator · Gift of Hope</p><button class="admin-button" data-profile-upload>Change photo</button><input type="file" accept="image/*" data-profile-input hidden></div></div><div class="admin-form-grid"><div class="admin-field"><label>Display name</label><input value="{{ Auth::user()->fname ?? 'Admin' }}"></div><div class="admin-field"><label>Position</label><input value="Program Administrator"></div><div class="admin-field" style="grid-column:1/-1"><label>Bio</label><input value="Helping communities turn generosity into measurable impact."></div></div></div>
            <div class="admin-settings-panel" data-settings-panel="preferences"><div class="admin-card-head" style="padding:0 0 18px"><div><h3>Preferences</h3><p>Personalize your workspace experience</p></div></div><div class="admin-setting-row"><div><strong>Dark mode</strong><span>Use a darker color theme throughout the admin workspace</span></div><label class="admin-switch"><input type="checkbox" data-dark-switch><i></i></label></div><div class="admin-setting-row"><div><strong>Notifications</strong><span>Receive in-app alerts for funding and IoT events</span></div><label class="admin-switch"><input type="checkbox" checked><i></i></label></div><div class="admin-setting-row"><div><strong>Weekly summary</strong><span>Show a weekly performance reminder in the workspace</span></div><label class="admin-switch"><input type="checkbox" checked><i></i></label></div><div class="admin-setting-row"><div><strong>Compact tables</strong><span>Reduce table row height to show more records</span></div><label class="admin-switch"><input type="checkbox" data-compact-switch><i></i></label></div><div class="admin-setting-row"><div><strong>Language</strong><span>Interface language for labels and reports</span></div><select class="admin-select"><option>English</option><option>Filipino</option></select></div></div>
            <div class="admin-settings-panel" data-settings-panel="security"><div class="admin-card-head" style="padding:0 0 18px;margin-bottom:16px"><div><h3>Security</h3><p>These dialogs demonstrate the verification experience only</p></div></div><div class="admin-notice" style="margin-bottom:16px">OTP verification is a UI simulation. No email, SMS, password, or account data will be changed.</div><div class="admin-security-item"><div>@</div><div><strong>Email address</strong><span>{{ Auth::user()->email ?? 'admin@giftofhope.org' }}</span></div><button class="admin-button" data-security-edit="email">Edit email</button></div><div class="admin-security-item"><div>☎</div><div><strong>Phone number</strong><span>+63 917 555 0184</span></div><button class="admin-button" data-security-edit="phone">Edit number</button></div><div class="admin-security-item"><div>⌘</div><div><strong>Password</strong><span>Last changed 42 days ago</span></div><button class="admin-button" data-security-edit="password">Change password</button></div></div>
        </div></div>
    </div>
</section>
@endsection