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
        <x-avatar class="hope-avatar hope-profile-avatar" />
        <h2 style="margin-top:16px">{{ trim((auth()->user()->fname ?? '').' '.(auth()->user()->lname ?? '')) ?: 'Community member' }}</h2>
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
        @foreach(['fname' => 'First name', 'lname' => 'Last name', 'email' => 'Email address', 'phone' => 'Phone number', 'address' => 'Address', 'date_of_birth' => 'Birthdate'] as $field => $label)
            <div>
                <dt>{{ $label }}</dt>
            <dd>{{ auth()->user()->$field ?: 'Not provided' }}</dd>
            </div>
        @endforeach
    </dl>
</section>
</div>
<div class="hope-grid hope-section">
    @foreach([['request-status','▤','Your requests','Track decisions, review feedback, and explore the appeal process.'],['notifications','◉','Your notifications','See updates on your requests.'],['dashboarduser','◎','Foundation updates','Announcements and funding news from Gift of Hope.']] as [$route,$icon,$title,$description])
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
