@extends('app')
@section('body')
@php($hopeDarkMode = filter_var(auth()->user()->dark_mode ?? false, FILTER_VALIDATE_BOOLEAN))
<div class="hope-shell @if($hopeDarkMode) hope-dark dark @endif" data-user-id="{{ auth()->id() }}" data-dark-mode="{{ $hopeDarkMode ? 'true' : 'false' }}">
<aside class="hope-sidebar" id="hope-navigation">
<a class="hope-brand" href="{{ route('dashboarduser') }}">♡ Gift of Hope<small>A little kindness. A lasting impact.</small></a>
<p class="hope-nav-label">YOUR COMMUNITY</p>
<nav aria-label="Main navigation">
@foreach (['dashboarduser' => ['⌂', 'Home feed'], 'groups' => ['◎', 'Groups'], 'fundraisers' => ['♡', 'Fundraisers'], 'fund-request' => ['＋', 'Fund request'], 'request-status' => ['▤', 'Request status'], 'activity' => ['↗', 'My activity'], 'notifications' => ['◉', 'Notifications'], 'user' => ['○', 'My profile'], 'settings' => ['⚙', 'Settings']] as $name => [$icon, $label])
<a href="{{ route($name) }}" @if(request()->routeIs($name, $name.'.*')) aria-current="page" @endif><span aria-hidden="true">{{ $icon }}</span>{{ $label }}</a>
@endforeach
<a href="{{ route('about') }}"><span aria-hidden="true">ⓘ</span>About Gift of Hope</a>
</nav>
<div class="hope-sidebar-bottom"><div class="hope-account"><span class="hope-avatar">{{ mb_substr(auth()->user()->fname ?? 'U', 0, 1) }}</span><div><strong>{{ auth()->user()->name ?? 'Guest' }}</strong><small>Community member</small></div></div><button type="button" class="hope-signout" data-open-dialog="logout-dialog">Sign out ↗</button></div>
</aside>
<div class="hope-main"><header class="hope-topbar"><button class="hope-menu" aria-label="Toggle navigation" aria-controls="hope-navigation" aria-expanded="false">☰</button><span>Small acts. <strong>Extraordinary change.</strong></span><a href="{{ route('notifications') }}">Notifications</a><a class="hope-avatar" href="{{ route('user') }}" aria-label="My profile">{{ mb_substr(auth()->user()->fname ?? 'U', 0, 1) }}</a></header><main id="main-content">@yield('content')</main></div>
<dialog id="logout-dialog" class="hope-dialog" aria-labelledby="logout-title"><button class="hope-close" data-close-dialog aria-label="Close">×</button><h2 id="logout-title">Ready to sign out?</h2><p>You can sign back in whenever you’re ready to make a difference.</p><div class="hope-actions"><button class="hope-button secondary" data-close-dialog>Stay signed in</button><form method="POST" action="/logout">@csrf<button class="hope-button" type="submit">Sign out</button></form></div></dialog>
<div class="hope-toast" role="status" aria-live="polite" hidden></div>
</div>
@endsection