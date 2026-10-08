@extends('app')
@section('body')
@php
    $hopeDarkMode = filter_var(auth()->user()->dark_mode ?? false, FILTER_VALIDATE_BOOLEAN);
    $navCount = count($navNotifications ?? []);
    $hopeNav = [
        'dashboarduser' => ['⌂', 'Home feed'],
        'fund-request' => ['＋', 'Fund request'],
        'request-status' => ['▤', 'Request status'],
        'activity' => ['↗', 'My activity'],
        'notifications' => ['◔', 'Notifications'],
        'user' => ['○', 'My profile'],
        'settings' => ['⚙', 'Settings'],
    ];
@endphp
<div class="hope-shell ws-shell @if($hopeDarkMode) hope-dark dark @endif" data-user-id="{{ auth()->id() }}" data-dark-mode="{{ $hopeDarkMode ? 'true' : 'false' }}">
<aside class="ws-sidebar" id="hope-navigation">
    <a class="ws-brand" href="{{ route('dashboarduser') }}"><span class="brand-tile"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="466" height="322"></span><span>Gift of Hope<small>A little kindness. A lasting impact.</small></span></a>
    <nav class="ws-nav" aria-label="Main navigation">
        <p class="ws-nav-label">YOUR COMMUNITY</p>
        @foreach ($hopeNav as $name => [$icon, $label])
            <a href="{{ route($name) }}" @if(request()->routeIs($name, $name.'.*')) aria-current="page" @endif><span aria-hidden="true">{{ $icon }}</span>{{ $label }}@if ($name === 'notifications' && $navCount)<b>{{ $navCount }}</b>@endif</a>
        @endforeach
        <a href="{{ route('about') }}"><span aria-hidden="true">ⓘ</span>About Gift of Hope</a>
    </nav>
    <div class="ws-person"><x-avatar class="ws-avatar" /><div><strong>{{ auth()->user()?->fullName() ?: 'Guest' }}</strong><small>Community member</small></div></div>
    <div class="ws-signout"><button type="button" data-open-dialog="logout-dialog">Sign out ↗</button></div>
</aside>
<div class="ws-main">
    <header class="ws-topbar">
        <button type="button" class="hope-menu ws-menu" aria-label="Open navigation" aria-controls="hope-navigation" aria-expanded="false">☰</button>
        <div class="ws-title"><small>Gift of Hope</small><strong>Small acts. Extraordinary change.</strong></div>
        <div class="ws-top-actions">
            @include('partials.notification-bell')
            <a href="{{ route('user') }}" class="ws-top-avatar" aria-label="My profile"><x-avatar class="ws-avatar" /></a>
        </div>
    </header>
    <main id="main-content" class="ws-content">@yield('content')</main>
</div>
<dialog id="logout-dialog" class="hope-dialog" aria-labelledby="logout-title"><button class="hope-close" data-close-dialog aria-label="Close">×</button><h2 id="logout-title">Ready to sign out?</h2><p>You can sign back in whenever you’re ready to make a difference.</p><div class="hope-actions"><button class="hope-button secondary" data-close-dialog>Stay signed in</button><form method="POST" action="{{ route('logout') }}">@csrf<button class="hope-button" type="submit">Sign out</button></form></div></dialog>
</div>
@endsection
