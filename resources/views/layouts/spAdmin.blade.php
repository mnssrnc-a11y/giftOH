@extends('app')
@section('body')
@php
    $saSection = trim($__env->yieldContent('sa-section'));
    $saNav = [
        'WORKSPACE' => ['dashboard' => ['▦', 'Dashboard'], 'requests' => ['₱', 'Fund requests'], 'accounts' => ['♧', 'Accounts'], 'prices' => ['▤', 'Price list']],
        'SYSTEM' => ['settings' => ['⚙', 'System settings'], 'monitor' => ['◉', 'System monitor'], 'activity' => ['◷', 'Activity log']],
    ];
    $awaitingCount = $outcomes['awaiting'] ?? null;
    $navCount = count($navNotifications ?? []);
@endphp
<div class="sa-shell ws-shell" data-superadmin>
    <aside class="ws-sidebar" id="sa-navigation">
        <a class="ws-brand" href="{{ route('superadmin') }}#dashboard"><span class="brand-tile"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="466" height="322"></span><span>Gift of Hope<small>Super admin</small></span></a>
        <nav class="ws-nav" aria-label="Superadmin navigation">
            @foreach ($saNav as $group => $links)
                <p class="ws-nav-label">{{ $group }}</p>
                @foreach ($links as $key => [$icon, $label])
                    <a href="{{ route('superadmin') }}#{{ $key }}" data-sa-nav="{{ $key }}" data-sa-label="{{ $label }}" @if ($saSection === $key) aria-current="page" @endif><span aria-hidden="true">{{ $icon }}</span>{{ $label }}@if ($key === 'requests' && $awaitingCount)<b>{{ $awaitingCount }}</b>@endif</a>
                @endforeach
            @endforeach
            <p class="ws-nav-label">MORE</p>
            <a href="{{ route('iot-monitor') }}" @if (request()->routeIs('iot-monitor')) aria-current="page" @endif><span aria-hidden="true">◫</span>IoT box monitor</a>
            <a href="{{ route('notifications') }}" @if (request()->routeIs('notifications')) aria-current="page" @endif><span aria-hidden="true">◔</span>Notifications @if ($navCount)<b>{{ $navCount }}</b>@endif</a>
            <a href="{{ route('settings') }}" @if (request()->routeIs('settings', 'user.edit', 'change-password.form')) aria-current="page" @endif><span aria-hidden="true">○</span>My account</a>
        </nav>
        <div class="ws-person">
            <x-avatar class="ws-avatar" fallback="S" />
            <div><strong>{{ auth()->user()->fullName() ?: 'Super admin' }}</strong><small>Super administrator</small></div>
        </div>
        <form class="ws-signout" method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out ↗</button></form>
    </aside>
    <div class="ws-main">
        <header class="ws-topbar">
            <button type="button" class="ws-menu" data-sa-menu aria-label="Open navigation" aria-expanded="false" aria-controls="sa-navigation">☰</button>
            <div class="ws-title"><small>Super admin</small><strong data-sa-title>@yield('page-title', 'Dashboard')</strong></div>
            <div class="ws-top-actions">
                @include('partials.notification-bell')
                <a href="{{ route('settings') }}" class="ws-top-avatar" aria-label="My account"><x-avatar class="ws-avatar" fallback="S" /></a>
            </div>
        </header>
        <main class="sa-content ws-content">
            @yield('content')
        </main>
    </div>
    <dialog class="sa-dialog" data-sa-dialog aria-labelledby="sa-dialog-title"><button class="sa-dialog-close" type="button" data-sa-close aria-label="Close dialog">×</button><div data-sa-dialog-content></div></dialog>
    {{-- Result of the last action, visible wherever the page is scrolled to. --}}
    @php($flashError = session('alert_error') ?? ($errors->any() ? $errors->first() : null))
    @if ($flashError || session('status'))
        <div class="sa-toast {{ $flashError ? 'is-error' : '' }}" role="{{ $flashError ? 'alert' : 'status' }}" data-sa-flash>
            <span>{{ $flashError ?? session('status') }}</span>
            <button type="button" data-sa-flash-close aria-label="Dismiss message">×</button>
        </div>
    @endif
</div>
@endsection
