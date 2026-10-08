@extends('layouts.spAdmin')
@section('title', 'Superadmin - Gift of Hope')
@section('content')
@php
    $pages = [
        'dashboard' => ['YOUR COMMUNITY, AT A GLANCE', 'A little oversight. A bigger impact.', 'Keep your community, funding, and connected devices moving forward.'],
        'accounts' => ['PEOPLE & PERMISSIONS', 'Account management', 'Every account in Firebase. Change roles and enable or disable access.'],
        'prices' => ['ITEMS & PRICES', 'Price list', 'Food, medical and cleaning items used in every per-person budget. Only you can add, edit or delete items and set prices.'],
        'settings' => ['PLATFORM CONTROLS', 'System settings', 'Scoring priorities, request interval, and email (Brevo).'],
        'monitor' => ['CONNECTED & INFORMED', 'System monitor', 'Device health, funding outcomes, and recent audit events.'],
        'requests' => ['MAKE HOPE HAPPEN', 'Fund requests', 'Every request, with the ones awaiting your final decision first. Open one to see everything the requester submitted and what the admins verified.'],
        'activity' => ['EVERY ACTION, ACCOUNTED FOR', 'Activity log', 'Account, configuration, funding and price changes in one place.'],
    ];
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
    $when = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('M d, Y g:i A') : '—';
    $ago = fn ($value) => $value ? \Carbon\Carbon::parse($value)->diffForHumans() : 'Never';
    $roleLabel = ['super_admin' => 'Super admin', 'admin' => 'Admin', 'user' => 'User'];
    $online = count(array_filter($devices, fn ($d) => $d['online']));
    $saConfig = [
        'availableFunds' => $availableFunds ?? null,
        'requestUrl' => route('superadmin.fund-request.show', ['id' => '__ID__']),
        // After a form fails validation the page reloads without its #section; reopen it.
        'initialSection' => old('_section'),
        'priceStatusUrl' => route('superadmin.prices.status'),
        'accountUrl' => route('superadmin.accounts.update', ['id' => '__ID__']),
        'accounts' => $accounts,
        'selfId' => $selfId,
        'prices' => collect($priceGroups)->flatMap(fn ($group) => collect($group['items'])->keyBy('key'))->all(),
        'priceGroups' => collect($priceGroups)->map(fn ($group) => ['label' => $group['label'], 'types' => collect($group['items'])->pluck('type')->unique()->values()->all()])->all(),
        'priceStoreUrl' => route('superadmin.prices.store'),
        'priceItemUrl' => route('superadmin.prices.update-item', ['key' => '__KEY__']),
    ];
    $deviceList = function () use ($devices, $ago) {
        if (! $devices) {
            return '<p class="sa-empty">No smart boxes have reported yet.</p>';
        }
        return collect($devices)->map(fn ($d) => '<div class="sa-device"><div><strong>' . e($d['id']) . '</strong><small>' . e($d['location']) . '</small><small>Last seen: ' . e($ago($d['last_seen'])) . ' · ₱' . number_format($d['total'], 2) . ' collected</small></div><span class="sa-badge ' . ($d['online'] ? '' : 'red') . '">' . ($d['online'] ? 'Online' : 'Offline') . '</span></div>')->implode('');
    };
    $eventList = function (int $limit) use ($logs, $when) {
        if (! $logs) {
            return '<p class="sa-empty">No activity recorded yet.</p>';
        }
        return collect(array_slice($logs, 0, $limit))->map(fn ($l) => '<div class="sa-event"><span class="sa-event-icon" aria-hidden="true">↗</span><div><strong>' . e($l['action']) . '</strong><small>' . e($l['actor']) . ' · ' . e($when($l['at'])) . '</small></div></div>')->implode('');
    };
@endphp
<script>
    window.giftOfHopeFinalizationQueue = @json($fundRequests ?? []);
    window.giftOfHopeSuperadmin = @json($saConfig);
</script>

@foreach ($pages as $key => [$eyebrow, $title, $description])
<section data-sa-page="{{ $key }}" @if ($key !== 'dashboard') hidden @endif>
    <div class="sa-heading"><div><span class="sa-eyebrow">{{ $eyebrow }}</span><h1>{{ $title }}</h1><p>{{ $description }}</p></div></div>

    @if ($key === 'dashboard' || $key === 'monitor')
    <div class="sa-stats">
        <article class="sa-stat"><div class="sa-stat-top">Total accounts<span class="sa-stat-icon">♧</span></div><strong>{{ $accountSummary['total'] }}</strong><small>{{ $accountSummary['admins'] }} admin(s) · {{ $accountSummary['disabled'] }} disabled</small></article>
        <article class="sa-stat"><div class="sa-stat-top">Connected devices<span class="sa-stat-icon">◉</span></div><strong>{{ $online }} <span class="sa-stat-of">/ {{ count($devices) }}</span></strong><small>{{ count($devices) - $online }} offline</small></article>
        <article class="sa-stat"><div class="sa-stat-top">Awaiting your decision<span class="sa-stat-icon">₱</span></div><strong>{{ $outcomes['awaiting'] }}</strong><small>Reviewed by admins</small></article>
        <article class="sa-stat"><div class="sa-stat-top">Approval rate<span class="sa-stat-icon">↗</span></div><strong>{{ $outcomes['rate'] }}%</strong><small>Of finalized requests</small></article>
    </div>
    @endif

    @if ($key === 'dashboard')
    <div class="sa-grid">
        <article class="sa-card">
            <div class="sa-card-head"><div><h2>Requests needing your decision</h2><p>Reviewed by an admin and waiting for you. Open one for the final check.</p></div><a class="sa-link" href="#requests">View all ↗</a></div>
            <div class="sa-table-wrap"><table class="sa-table"><thead><tr><th>Organization / reviewed by</th><th>Amount</th><th>Review</th></tr></thead><tbody data-sa-request-preview></tbody></table></div>
        </article>
        <article class="sa-card">
            <div class="sa-card-head"><h2>AI scoring priorities</h2><a class="sa-link" href="#settings">Manage ↗</a></div>
            <div class="sa-card-body">
                @foreach ($scores as $name => $score)
                    <div class="sa-score"><div><span>{{ $categoryLabels[$name] ?? $name }}</span><strong>{{ (int) $score }} / 100</strong></div><progress value="{{ $score }}" max="100" aria-label="{{ $name }} score"></progress></div>
                @endforeach
                <div class="sa-note">Priorities shape the AI fund recommendations. Final decisions stay with your team.</div>
            </div>
        </article>
    </div>
    <div class="sa-grid equal">
        <article class="sa-card"><div class="sa-card-head"><h2>Connected donation boxes</h2><a href="#monitor" class="sa-link">View monitor ↗</a></div><div class="sa-card-body">{!! $deviceList() !!}</div></article>
        <article class="sa-card"><div class="sa-card-head"><h2>Recent activity</h2><a href="#activity" class="sa-link">View log ↗</a></div><div class="sa-card-body">{!! $eventList(5) !!}</div></article>
    </div>

    @elseif ($key === 'accounts')
    <article class="sa-card">
        <div class="sa-card-head"><h2>Accounts</h2><span class="sa-badge gray">{{ $accountSummary['total'] }} in Firebase</span></div>
        <div class="sa-toolbar"><input type="search" data-sa-filter-search="accounts" placeholder="Search by name or email" aria-label="Search accounts"><select data-sa-filter-select="accounts" data-key="role" aria-label="Filter role"><option value="">All roles</option><option value="user">User</option><option value="admin">Admin</option><option value="super_admin">Super admin</option></select><select data-sa-filter-select="accounts" data-key="status" aria-label="Filter status"><option value="">All statuses</option><option value="enabled">Enabled</option><option value="disabled">Disabled</option></select></div>
        <div class="sa-table-wrap"><table class="sa-table"><thead><tr><th>Account</th><th>Role</th><th>Access</th><th>Last sign-in</th><th>Action</th></tr></thead><tbody data-sa-filter-body="accounts">
            @foreach ($accounts as $account)
                <tr data-role="{{ $account['role'] }}" data-status="{{ $account['active'] ? 'enabled' : 'disabled' }}" data-text="{{ strtolower($account['name'] . ' ' . $account['email']) }}">
                    <td><strong>{{ $account['name'] }}@if ($account['id'] === $selfId) <span class="sa-badge gray">You</span>@endif</strong><small>{{ $account['email'] }}</small></td>
                    <td>{{ $roleLabel[$account['role']] ?? $account['role'] }}</td>
                    <td><span class="sa-badge {{ $account['active'] ? '' : 'red' }}">{{ $account['active'] ? 'Enabled' : 'Disabled' }}</span></td>
                    <td>{{ $ago($account['last_login']) }}<small>Joined {{ $account['created_at'] ? \Carbon\Carbon::parse($account['created_at'])->format('M Y') : '—' }}</small></td>
                    <td>@if ($account['role'] !== 'super_admin' && $account['id'] !== $selfId)<button class="sa-link" data-sa-account="{{ $account['id'] }}">Manage ↗</button>@else<small>—</small>@endif</td>
                </tr>
            @endforeach
        </tbody></table></div>
        <p class="sa-empty" data-sa-filter-empty="accounts" hidden>No accounts match.</p>
    </article>

    @elseif ($key === 'prices')
    @php($run = $priceRun)
    <article class="sa-card">
        <div class="sa-card-head"><div><h2>Monthly AI price update</h2><p>Runs on the 1st of every month: the <a class="sa-link" href="https://www.dti.gov.ph/dti-consumer-space/dti-latest-srps-basic-necessities-prime-commodities" target="_blank" rel="noopener">DTI SRP bulletin</a> for food and cleaning materials, the <a class="sa-link" href="https://tgp.com.ph/" target="_blank" rel="noopener">TGP store</a> for medicine, and web search for the rest.</p></div><span class="sa-badge {{ $priceUpdateRunning ? 'amber' : (($priceUpdateStatus['state'] ?? '') === 'failed' ? 'red' : 'gray') }}" data-price-status data-running="{{ $priceUpdateRunning ? 'true' : 'false' }}">{{ $priceUpdateRunning ? 'Running now' : ($run ? 'Last run ' . $ago($run['ran_at']) : 'Not run yet') }}</span></div>
        <div class="sa-card-body">
            @if ($priceUpdateRunning)<div class="sa-note" style="margin:0 0 14px">Updating prices in the background since {{ $ago($priceUpdateStatus['started_at'] ?? null) }}. This takes a few minutes; the page refreshes when it is done and you get a notification.</div>
            @elseif (($priceUpdateStatus['state'] ?? '') === 'failed')<div class="sa-note sa-note-error" style="margin:0 0 14px">{{ $priceUpdateStatus['message'] ?? 'The last update failed.' }}</div>@endif
            @if ($run && ! empty($run['failed']))<p style="margin-top:0">Last run stopped: {{ $run['failed'] }}</p>
            @elseif ($run)<p style="margin-top:0"><strong>{{ count((array) ($run['updated'] ?? [])) }}</strong> price(s) changed · {{ count((array) ($run['not_found'] ?? [])) }} not found · {{ count((array) ($run['flagged'] ?? [])) }} flagged for review.</p>
                @if (! empty($run['flagged']))<ul style="margin:0 0 12px;padding-left:18px;font-size:12px">@foreach ($run['flagged'] as $flag)<li>{{ $flag['name'] }}: {{ $flag['reason'] }}</li>@endforeach</ul>@endif
            @endif
            <form method="POST" action="{{ route('superadmin.prices.update') }}" data-sa-confirm="Run the AI price update now? Prices found on DTI, TGP and web search replace the current prices; you can still edit any price afterwards.">
                @csrf
                <div class="sa-actions" style="justify-content:flex-start;margin-top:0"><button class="sa-button" @disabled($priceUpdateRunning)>↻ Update prices now</button></div>
            </form>
            <div class="sa-note">Admins see this list and pick items and quantities for each request's budget, but they cannot add items or change prices.</div>
        </div>
    </article>
    @foreach ($priceGroups as $group)
    <article class="sa-card" id="prices-{{ $group['key'] }}">
        <div class="sa-card-head"><div><h2>{{ $group['label'] }}</h2><p>{{ count($group['items']) }} item(s) · Firebase price_list/{{ $group['key'] }}</p></div><button type="button" class="sa-button secondary" data-sa-price-add="{{ $group['key'] }}">+ Add item</button></div>
        <div class="sa-toolbar"><input type="search" data-sa-filter-search="prices-{{ $group['key'] }}" placeholder="Search {{ strtolower($group['label']) }}" aria-label="Search {{ $group['label'] }} items"></div>
        <div class="sa-table-wrap"><table class="sa-table"><thead><tr><th>Item</th><th>Type</th><th>Budget price</th><th>Range</th><th>Source</th><th>Updated</th><th>Action</th></tr></thead><tbody data-sa-filter-body="prices-{{ $group['key'] }}">
            @forelse ($group['items'] as $item)
                <tr data-text="{{ strtolower($item['name'] . ' ' . $item['size'] . ' ' . $item['type']) }}">
                    <td><strong>{{ $item['name'] }}</strong><small>{{ $item['size'] }}</small></td>
                    <td style="white-space:normal;min-width:110px;max-width:170px">{{ $item['type'] }}</td>
                    <td><strong>{{ $peso($item['price']) }}</strong>@if ($item['previous_price'] !== null)<small>was {{ $peso($item['previous_price']) }}</small>@endif</td>
                    <td>{{ $peso($item['min']) }}–{{ $peso($item['max']) }}</td>
                    <td style="white-space:normal;min-width:120px;max-width:180px">@if ($item['source_url'])<a class="sa-link" href="{{ $item['source_url'] }}" target="_blank" rel="noopener">{{ $item['source'] }}</a>@else{{ $item['source'] ?? '—' }}@endif</td>
                    <td>{{ $item['updated_at'] ? \Carbon\Carbon::parse($item['updated_at'])->format('M d, Y') : '—' }}</td>
                    <td><button type="button" class="sa-link" data-sa-price-edit="{{ $item['key'] }}">Edit ↗</button></td>
                </tr>
            @empty
                <tr><td colspan="7" class="sa-empty">No items yet. Add the first one.</td></tr>
            @endforelse
        </tbody></table></div>
        <p class="sa-empty" data-sa-filter-empty="prices-{{ $group['key'] }}" hidden>No items match.</p>
    </article>
    @endforeach

    @elseif ($key === 'settings')
    <div class="sa-grid equal">
        <article class="sa-card"><div class="sa-card-head"><h2>AI scoring priorities</h2><span class="sa-badge">0–100 per category</span></div>
            <form class="sa-card-body" method="POST" action="{{ route('superadmin.settings.scores') }}" data-sa-confirm="Save the new category priority scores?">
                @csrf<input type="hidden" name="_section" value="settings">
                <p>Higher scores get a larger share when funds are limited.</p>
                <div class="sa-fields" style="margin-top:20px">
                    @foreach ($scores as $name => $score)<label class="sa-field">{{ $categoryLabels[$name] ?? $name }}<input name="scores[{{ \Illuminate\Support\Str::slug($name) }}]" type="number" min="0" max="100" step="1" value="{{ (int) $score }}" required></label>@endforeach
                </div>
                <div class="sa-actions"><button class="sa-button">Save scores</button></div>
            </form>
        </article>
        <article class="sa-card"><div class="sa-card-head"><h2>Request interval</h2><span class="sa-badge gray">Per account</span></div>
            <form class="sa-card-body" method="POST" action="{{ route('superadmin.settings.interval') }}" data-sa-confirm="Change the minimum time between requests?">
                @csrf<input type="hidden" name="_section" value="settings">
                <p>Minimum days between two requests from the same account.</p>
                <div class="sa-note">Current: <strong>{{ $intervalDays }} days</strong></div>
                <label class="sa-field" style="margin-top:20px">New interval (days)<input type="number" name="days" min="{{ $minIntervalDays }}" max="1000" step="1" value="{{ $intervalDays }}" required><small>Must be greater than 92 days.</small></label>
                <div class="sa-actions"><button class="sa-button">Save interval</button></div>
            </form>
        </article>
    </div>
    <article class="sa-card" id="settings-mail">
        <div class="sa-card-head"><div><h2>Verification email settings</h2><p>{{ $mail['driver'] === 'brevo' ? 'Verification codes and notices are sent through Brevo. Save your Brevo API key and the sender address here.' : 'Verification codes and notices are sent through Gmail SMTP (MAIL_* in .env).' }}</p></div><span class="sa-badge {{ $mail['password_set'] ? '' : 'red' }}">{{ $mail['password_set'] ? 'Set up' : ($mail['driver'] === 'brevo' ? 'No API key yet' : 'No password set') }}</span></div>
        <div class="sa-card-body">
            <dl class="sa-facts" style="margin-bottom:18px">
                <div><dt>Service</dt><dd>{{ $mail['driver'] === 'brevo' ? 'Brevo (HTTPS API)' : $mail['host'] . ':' . $mail['port'] }}</dd></div>
                <div><dt>Sender</dt><dd>{{ $mail['from_address'] ?: '—' }}<small>Shown as “{{ $mail['from_name'] ?: '—' }}”</small></dd></div>
                @if ($mail['driver'] === 'brevo')<div><dt>API key</dt><dd>{{ $mail['key_hint'] ? 'Saved, ending in ' . $mail['key_hint'] : 'Not saved' }}<small>Stored encrypted; never shown in full</small></dd></div>@endif
            </dl>
            @if ($mailPending)
                <form method="POST" action="{{ route('superadmin.settings.mail.confirm') }}">
                    @csrf<input type="hidden" name="_section" value="settings-mail">
                    <p>We sent a 6-digit code to <strong>{{ $mailPending['email'] }}</strong> using the new settings. Enter it to save them.</p>
                    <div class="sa-fields"><label class="sa-field">Verification code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label></div>
                    <div class="sa-actions"><button class="sa-button secondary" name="cancel" value="1" formnovalidate>Cancel</button><button class="sa-button">Confirm and save</button></div>
                </form>
            @else
                <form method="POST" action="{{ route('superadmin.settings.mail') }}" autocomplete="off">
                    @csrf<input type="hidden" name="_section" value="settings-mail">
                    <div class="sa-fields">
                        @if ($mail['driver'] === 'brevo')
                        <label class="sa-field full">Brevo API key{{ $mail['key_hint'] ? ' (leave empty to keep the saved key)' : '' }}<input type="password" name="api_key" autocomplete="new-password" placeholder="xkeysib-…" @if (! $mail['key_hint']) required @endif><small>In Brevo: SMTP &amp; API → API keys → Generate a new API key. It starts with “xkeysib-”.</small></label>
                        @endif
                        <label class="sa-field">Sender email<input type="email" name="email" value="{{ old('email', $mail['from_address']) }}" required><small>{{ $mail['driver'] === 'brevo' ? 'Must be a verified sender in Brevo (Senders, domains & IPs).' : 'The Gmail address that sends codes.' }}</small></label>
                        <label class="sa-field">Name shown to recipients<input name="app_name" value="{{ old('app_name', $mail['from_name']) }}" maxlength="80" required></label>
                        @if ($mail['driver'] !== 'brevo')
                        <label class="sa-field full">Gmail app password<input type="password" name="password" autocomplete="new-password" minlength="8" required><small>Create one at myaccount.google.com/apppasswords (2-Step Verification must be on). It is never shown again.</small></label>
                        @endif
                    </div>
                    <p class="sa-muted" style="margin-top:14px">We send a 6-digit code to the sender email with these settings. They are saved only after you enter the code.</p>
                    <div class="sa-actions"><button class="sa-button">Send verification code</button></div>
                </form>
                @if ($mail['password_set'])
                    <form method="POST" action="{{ route('superadmin.settings.mail.test') }}" class="sa-inline-form">@csrf<button class="sa-button secondary" type="submit">Send a test email to {{ auth()->user()->email }}</button></form>
                @endif
            @endif
        </div>
    </article>

    @elseif ($key === 'monitor')
    <div class="sa-grid equal">
        <article class="sa-card"><div class="sa-card-head"><h2>IoT devices</h2><a class="sa-link" href="{{ route('iot-monitor') }}">Live monitor ↗</a></div><div class="sa-card-body">{!! $deviceList() !!}</div></article>
        <article class="sa-card"><div class="sa-card-head"><h2>Funding outcomes</h2><a class="sa-link" href="#requests">Review requests ↗</a></div><div class="sa-card-body">
            <div class="sa-big-number">{{ $peso($outcomes['amount']) }}</div><p>Total granted</p>
            <div class="sa-outcomes"><span><b>{{ $outcomes['approved'] }}</b>Approved</span><span><b>{{ $outcomes['rejected'] }}</b>Rejected</span><span><b>{{ $outcomes['awaiting'] }}</b>Awaiting you</span></div>
            <progress max="100" value="{{ $outcomes['rate'] }}" aria-label="Approval percentage"></progress>
            <div class="sa-note">Approval rate counts finalized requests only.</div>
        </div></article>
    </div>
    <article class="sa-card"><div class="sa-card-head"><h2>Audit events</h2><a class="sa-link" href="#activity">Full log ↗</a></div><div class="sa-card-body">{!! $eventList(8) !!}</div></article>

    @elseif ($key === 'requests')
    <article class="sa-card"><div class="sa-card-head"><h2>All fund requests</h2><span class="sa-badge amber">{{ $outcomes['awaiting'] }} awaiting you</span></div><div class="sa-toolbar"><input type="search" data-sa-request-search placeholder="Search organization, administrator, or category" aria-label="Search fund requests"><select data-sa-request-filter aria-label="Filter request status"><option value="">All statuses</option><option>Awaiting you</option><option>With admins</option><option>Approved</option><option>Completed</option><option>Rejected</option></select></div><div class="sa-table-wrap"><table class="sa-table"><thead><tr><th>Request / requester</th><th>Category</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody data-sa-requests></tbody></table></div></article>

    @elseif ($key === 'activity')
    <article class="sa-card"><div class="sa-card-head"><h2>Activity log</h2><span class="sa-badge gray">{{ count($logs) }} recent event(s)</span></div>
        <div class="sa-toolbar"><input type="search" data-sa-filter-search="logs" placeholder="Search actions or people" aria-label="Search activity log"><select data-sa-filter-select="logs" data-key="category" aria-label="Filter category"><option value="">All categories</option>@foreach (['account' => 'Accounts', 'settings' => 'Settings', 'funding' => 'Funding', 'prices' => 'Prices', 'security' => 'Security'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div class="sa-table-wrap"><table class="sa-table"><thead><tr><th>Action</th><th>By</th><th>When</th><th>Category</th></tr></thead><tbody data-sa-filter-body="logs">
            @forelse ($logs as $log)
                <tr data-category="{{ $log['category'] }}" data-text="{{ strtolower($log['action'] . ' ' . $log['actor']) }}"><td>{{ $log['action'] }}</td><td>{{ $log['actor'] }}</td><td>{{ $when($log['at']) }}</td><td><span class="sa-badge gray">{{ ucfirst($log['category']) }}</span></td></tr>
            @empty
                <tr><td colspan="4" class="sa-empty">No activity recorded yet. Actions are logged from now on.</td></tr>
            @endforelse
        </tbody></table></div>
        <p class="sa-empty" data-sa-filter-empty="logs" hidden>No events match.</p>
    </article>
    @endif
</section>
@endforeach
@endsection
