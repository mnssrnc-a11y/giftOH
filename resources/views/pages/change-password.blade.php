@extends(\App\Support\Layout::forRole())
@section('title', 'Change password - Gift of Hope')
@section('page-kicker', 'Account')
@section('page-title', 'Change password')
@section('content')
<div class="hope-page">
    <div class="hope-heading"><div>
        <div class="hope-eyebrow">ACCOUNT SECURITY</div>
        <h1>Change your password.</h1>
        <p>Use at least 8 characters. You stay signed in on this device.</p>
    </div></div>

    <section class="hope-card" style="max-width:560px">
        @if ($errors->any())
            <div class="hope-error" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif
        <form method="POST" action="{{ route('change-password') }}">
            @csrf
            <div class="hope-field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
            <div class="hope-field"><label for="password">New password</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
            <div class="hope-field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"></div>
            <div class="hope-actions"><a class="hope-button secondary" href="{{ route('settings') }}">Cancel</a><button class="hope-button" type="submit">Update password</button></div>
        </form>
    </section>
</div>
@endsection
