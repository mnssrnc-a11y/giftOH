@extends('layouts.public')

@section('title', 'Gift of Hope')

@section('content')
<div class="brand-public">
    @if (session('alert_error'))
        <div class="brand-public-alert" role="alert">{{ session('alert_error') }}</div>
    @endif

    <section class="brand-hero">
        <div class="brand-hero-inner">
            <div>
                <p class="brand-kicker">CHARITY HOME PLATFORM</p>
                <h1>Give hope, change lives.</h1>
                <p class="brand-hero-lead">A transparent charity platform with smart IoT donation boxes. Every donation is tracked, verified, and makes a real difference.</p>
                <button type="button" class="brand-btn brand-btn-light" data-start-open aria-controls="account-choice" aria-haspopup="dialog">Get started</button>
            </div>
            <div class="landing-logo">
                <img src="{{ asset('images/logo-full.png') }}" alt="Gift of Hope, charity home platform" width="768" height="538">
            </div>
        </div>
    </section>

    @if (! empty($posts))
        <section class="brand-section" aria-labelledby="latest-updates-title">
            <div class="brand-section-head">
                <h2 id="latest-updates-title">Latest updates</h2>
                <p>News and funding updates from the Gift of Hope team.</p>
            </div>
            <div class="brand-updates">@include('partials.announcements', ['posts' => $posts])</div>
        </section>
    @endif

    <section class="brand-section brand-section-tint">
        <div class="brand-section-head">
            <h2>Why Gift of Hope?</h2>
            <p>Technology that keeps compassionate giving honest.</p>
        </div>
        <div class="brand-features">
            @foreach ([
                ['◎', 'Complete transparency', 'See where every contribution goes, from the donation box to the families it reaches.'],
                ['◷', 'Real-time tracking', 'Smart donation boxes report what they collect as it happens.'],
                ['✦', 'Careful review', 'Social workers assess every request, and two levels of approval check it before funds are released.'],
            ] as [$icon, $title, $text])
                <article class="brand-feature"><span aria-hidden="true">{{ $icon }}</span><h3>{{ $title }}</h3><p>{{ $text }}</p></article>
            @endforeach
        </div>
    </section>

    <section class="brand-cta">
        <div class="landing-mark"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="466" height="322"></div>
        <h2>Ready to make a difference?</h2>
        <p>Request help for your community, or follow how donations are put to work.</p>
        <button type="button" class="brand-btn brand-btn-light" data-start-open aria-controls="account-choice" aria-haspopup="dialog">Join us now</button>
    </section>
</div>

<dialog id="account-choice" class="brand-dialog" aria-labelledby="account-choice-title">
    <h2 id="account-choice-title">Do you have an account?</h2>
    <p>Choose how you would like to continue.</p>
    <div class="brand-dialog-actions">
        <a href="{{ route('login') }}" class="brand-btn">Yes, sign in</a>
        <a href="{{ route('register') }}" class="brand-btn brand-btn-outline">No, create one</a>
    </div>
    <button type="button" class="brand-link" data-start-close>Cancel</button>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('account-choice');
        document.querySelectorAll('[data-start-open]').forEach(button => button.addEventListener('click', () => dialog.showModal()));
        dialog.querySelector('[data-start-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    })();
</script>
@endsection
