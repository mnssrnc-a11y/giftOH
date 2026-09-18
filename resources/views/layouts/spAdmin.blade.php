@extends('app')
@section('body')

<div class="sa-shell" data-superadmin>
    <aside class="sa-sidebar" id="sa-navigation">
        <a class="sa-brand" href="#dashboard"><span class="sa-logo">GH</span><span>Gift of Hope<small>SUPERADMIN WORKSPACE</small></span></a>
        <p class="sa-nav-label">MANAGEMENT</p>
        <nav aria-label="Superadmin navigation">
            @foreach(['dashboard' => ['▦', 'Dashboard'], 'accounts' => ['♧', 'Accounts'], 'settings' => ['⚙', 'System settings'], 'monitor' => ['◉', 'System monitor'], 'requests' => ['₱', 'Fund requests'], 'activity' => ['◷', 'Activity log']] as $key => [$icon, $label])
            <a href="#{{ $key }}" data-sa-nav="{{ $key }}"><span aria-hidden="true">{{ $icon }}</span>{{ $label }}@if($key === 'requests')<b data-sa-pending>3</b>@endif</a>
            @endforeach
        </nav>
        <div class="sa-side-bottom"><div class="sa-side-note">A little kindness.<br><strong>A lasting impact.</strong></div><div class="sa-person"><span class="sa-avatar">{{ mb_substr(auth()->user()->fname ?? 'A', 0, 1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>Superadmin UI preview</small></div></div><a class="sa-back" href="{{ route('user') }}">Back to my account ↗</a></div>
    </aside>
    <div class="sa-main">
        <header class="sa-topbar"><div class="sa-breadcrumb"><button type="button" class="sa-menu" aria-label="Toggle navigation" aria-expanded="false" aria-controls="sa-navigation">☰</button><span>Workspace / <strong data-sa-title>Dashboard</strong></span></div><div class="sa-top-right"><span class="sa-badge">Superadmin</span><span class="sa-avatar">{{ mb_substr(auth()->user()->fname ?? 'A', 0, 1) }}</span></div></header>
        <main class="sa-content">
            <div class="sa-preview"><strong>UI preview</strong><span>Sample data. Changes last until reload; no accounts, emails, or system records are changed.</span></div>
            @yield('content')
        </main>
    </div>
    <dialog class="sa-dialog" data-sa-dialog aria-labelledby="sa-dialog-title"><button class="sa-dialog-close" type="button" data-sa-close aria-label="Close dialog">×</button><div data-sa-dialog-content></div></dialog>
    <div class="sa-toast" role="status" aria-live="polite" hidden></div>
</div>

@endsection
