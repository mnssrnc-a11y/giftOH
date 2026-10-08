@extends('app')

@section('body')
@php($navCount = count($navNotifications ?? []))
<div id="adminApp" class="admin-shell ws-shell" data-admin-app>
    <aside class="ws-sidebar" data-sidebar id="admin-navigation">
        <div class="ws-brand-row">
            <a class="ws-brand" href="{{ route('admin') }}#dashboard"><span class="brand-tile"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="466" height="322"></span><span>Gift of Hope<small>Admin</small></span></a>
            <button type="button" class="admin-sidebar-close" data-sidebar-toggle aria-label="Close navigation">&times;</button>
        </div>
        <nav class="ws-nav" aria-label="Admin navigation">
            <p class="ws-nav-label">WORKSPACE</p>
            <a href="{{ route('admin') }}#dashboard" data-admin-nav="dashboard" class="{{ request()->routeIs('admin') ? 'is-active' : '' }}"><span aria-hidden="true">⌂</span>Dashboard</a>
            <a href="{{ route('admin') }}#funding" data-admin-nav="funding" class="{{ request()->routeIs('admin.fund-request.*') ? 'is-active' : '' }}"><span aria-hidden="true">₱</span>Funding @if ($pendingCount ?? 0)<b data-pending-count>{{ $pendingCount }}</b>@endif</a>
            <a href="{{ route('admin') }}#updates" data-admin-nav="updates"><span aria-hidden="true">✎</span>Updates</a>
            <a href="{{ route('admin') }}#reports" data-admin-nav="reports"><span aria-hidden="true">▥</span>Reports</a>
            <p class="ws-nav-label">MONITORING</p>
            <a href="{{ route('iot-monitor') }}" class="{{ request()->routeIs('iot-monitor') ? 'is-active' : '' }}"><span aria-hidden="true">◫</span>IoT box monitor</a>
            <a href="{{ route('donations') }}" class="{{ request()->routeIs('donations') ? 'is-active' : '' }}"><span aria-hidden="true">♡</span>Donations</a>
            <p class="ws-nav-label">ACCOUNT</p>
            <a href="{{ route('notifications') }}" class="{{ request()->routeIs('notifications') ? 'is-active' : '' }}"><span aria-hidden="true">◔</span>Notifications @if ($navCount)<b>{{ $navCount }}</b>@endif</a>
            <a href="{{ route('admin') }}#settings" data-admin-nav="settings"><span aria-hidden="true">⚙</span>Settings</a>
        </nav>
        <div class="ws-person">
            <x-avatar class="ws-avatar" fallback="A" />
            <div><strong>{{ Auth::user()->fullName() ?: 'Admin' }}</strong><small>Administrator</small></div>
        </div>
        <form class="ws-signout" method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out ↗</button></form>
    </aside>
    <button type="button" class="admin-sidebar-scrim" data-sidebar-toggle aria-label="Close navigation"></button>

    <main class="admin-main ws-main">
        <header class="ws-topbar">
            <button type="button" class="ws-menu" data-sidebar-toggle aria-label="Open navigation" aria-controls="admin-navigation">☰</button>
            <div class="ws-title"><small data-page-kicker>@yield('page-kicker', 'Overview')</small><strong data-page-title>@yield('page-title', 'Dashboard')</strong></div>
            <div class="ws-top-actions">
                <label class="admin-global-search"><span aria-hidden="true">⌕</span><input type="search" placeholder="Search workspace" aria-label="Search workspace" data-global-search></label>
                <button type="button" class="ws-icon-button" data-theme-toggle title="Dark mode" aria-label="Toggle dark mode">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg>
                </button>
                @include('partials.notification-bell')
                <a href="{{ route('admin') }}#settings" class="ws-top-avatar" aria-label="My account"><x-avatar class="ws-avatar" fallback="A" /></a>
            </div>
        </header>
        <div class="admin-content ws-content">@yield('content')</div>
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
