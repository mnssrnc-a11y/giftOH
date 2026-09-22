@extends('layouts.dashboard')
@section('title', 'My profile - Gift of Hope')
@section('content')
<div class="hope-page">
<div class="hope-heading">
    <div>
        <div class="hope-eyebrow">MY PROFILE</div>
    <h1>A little about you.</h1>
    <p>Your place in the Gift of Hope community.</p>
    </div>
    <a href="{{ route('settings') }}" class="hope-button">Edit profile</a>
</div>
<div class="hope-grid two">
    <section class="hope-card">
        <div class="hope-profile-cover"></div>
        <span class="hope-avatar hope-profile-avatar">{{ mb_substr(auth()->user()->fname ?? 'U', 0, 1) }}</span>
        <h2 style="margin-top:16px">{{ auth()->user()->name }}</h2>
        <p>{{ auth()->user()->email }}</p>
        <div class="hope-meta">
            <span class="hope-badge">Community member</span>
            <span>Member since {{ filled(auth()->user()->created_at) ? \Illuminate\Support\Carbon::parse(auth()->user()->created_at)->format('M Y') : '—' }}</span>
        </div>
        <p>Every act of kindness starts with someone like you.</p>
        <div class="hope-actions">
            <a class="hope-button secondary" href="{{ route('activity') }}">View my activity →</a>
        </div>
    </section>
<section class="hope-card">
    <h2>Personal information</h2>
    <p>Keep your information current in Settings.</p>
    <dl class="hope-detail">
        @foreach(['Full name' => auth()->user()->name, 'Email address' => auth()->user()->email, 'Phone number' => auth()->user()->phone, 'Address' => auth()->user()->address, 'Birthdate' => auth()->user()->date_of_birth] as $label => $value)
            <div>
                <dt>{{ $label }}</dt>
                <dd>{{ $value ?: 'Not provided' }}</dd>
            </div>
        @endforeach
    </dl>
</section>
</div>
<div class="hope-grid hope-section">
    @foreach([['request-status','▤','Your requests','Track decisions, review feedback, and explore the appeal process.'],['notifications','◉','Your notifications','See request updates and community milestones.'],['groups','◎','Your community','Meet people who are making a difference together.']] as [$route,$icon,$title,$description])
        <section class="hope-card">
            <span class="hope-avatar" aria-hidden="true">{{ $icon }}</span>
            <h2 style="margin-top:16px">{{ $title }}</h2>
            <p>{{ $description }}</p>
            <div class="hope-actions">
                <a class="hope-text-button" href="{{ route($route) }}">Explore →</a>
            </div>
        </section>
    @endforeach
</div>
</div>
@endsection
