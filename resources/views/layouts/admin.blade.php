@extends('app')

@section('body')
<div id="adminApp" class="admin-shell" data-admin-app>
    <div class="admin-mobile-bar">
        <button type="button" class="admin-icon-button" data-sidebar-toggle aria-label="Open navigation">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <a href="#dashboard" class="admin-mobile-brand">Gift of Hope</a>
        <button type="button" class="admin-icon-button" data-theme-toggle aria-label="Toggle dark mode">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg>
        </button>
    </div>

    <aside class="admin-sidebar" data-sidebar>
        <div class="admin-brand">
            <div class="admin-brand-mark">GH</div>
            <div><strong>Gift of Hope</strong><span>Admin workspace</span></div>
            <button type="button" class="admin-sidebar-close" data-sidebar-toggle aria-label="Close navigation">&times;</button>
        </div>
        <nav class="admin-nav" aria-label="Admin navigation">
            <p>Workspace</p>
            <a href="#dashboard" data-admin-nav="dashboard" class="is-active"><span>⌂</span>Dashboard</a>
            <a href="#funding" data-admin-nav="funding"><span>₱</span>Funding <b data-pending-count>{{ $pendingRequests->count() ?: 4 }}</b></a>
            <a href="#reports" data-admin-nav="reports"><span>▥</span>Reports</a>
            <a href="#settings" data-admin-nav="settings"><span>⚙</span>Settings</a>
            <p>Monitoring</p>
            <a href="{{ route('iot-monitor') }}"><span>◫</span>IoT Box Monitor</a>
            <a href="{{ route('donations') }}"><span>♡</span>Donations</a>
        </nav>
        <div class="admin-user-card">
            <div class="admin-avatar">{{ strtoupper(substr(Auth::user()->fname ?? Auth::user()->name ?? 'A', 0, 1)) }}</div>
            <div><strong>{{ Auth::user()->name ?? trim((Auth::user()->fname ?? 'Admin').' '.(Auth::user()->lname ?? '')) }}</strong><span>Administrator</span></div>
            <a href="{{ route('user') }}" aria-label="Open profile">›</a>
        </div>
        <div class="admin-sidebar-footer">© 2026 Gift of Hope</div>
    </aside>
    <button type="button" class="admin-sidebar-scrim" data-sidebar-toggle aria-label="Close navigation"></button>

    <main class="admin-main">
        <header class="admin-topbar">
            <div><p data-page-kicker>Overview</p><h1 data-page-title>Dashboard</h1></div>
            <div class="admin-top-actions">
                <label class="admin-global-search"><span>⌕</span><input type="search" placeholder="Search workspace" data-global-search></label>
                <button type="button" class="admin-icon-button admin-desktop-theme" data-theme-toggle title="Toggle dark mode">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg>
                </button>
                <button type="button" class="admin-icon-button has-dot" data-notification-open title="Notifications" aria-label="Open notifications">♢</button>
            </div>
        </header>
        <div class="admin-content">@yield('content')</div>
    </main>

    <div class="admin-snackbar" data-snackbar-box role="status" aria-live="polite"></div>
    <div class="admin-modal" data-modal hidden>
        <button class="admin-modal-backdrop" data-modal-close aria-label="Close dialog"></button>
        <section class="admin-modal-panel" role="dialog" aria-modal="true" aria-labelledby="adminModalTitle">
            <button type="button" class="admin-modal-close" data-modal-close aria-label="Close dialog">&times;</button>
            <div data-modal-content></div>
        </section>
    </div>
</div>
@endsection
