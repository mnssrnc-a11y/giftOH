@extends(\App\Support\Layout::forRole())
@section('title', 'Settings - Gift of Hope')
@section('page-kicker', 'Account')
@section('page-title', 'Settings')
@section('content')
<div class="hope-page">
    <div class="hope-heading"><div>
    <div class="hope-eyebrow">SETTINGS</div>
    <h1>Make yourself at home.</h1>
    <p>Manage your profile, preferences, and account security.</p>
    </div>
</div>
{{-- Preferences, account details and password changes all return here with a "status" message. --}}
@if (session('status'))
<div class="hope-preview" role="status">{{ session('status') }}</div>
@endif
<div class="hope-grid two">
    <section class="hope-card">
        <h2>Profile picture</h2>
        <p>Give your community a familiar face.</p>
@php($currentPicture = auth()->user()?->profilePhotoUrl())
@if(session('profile_picture_status'))
<div class="hope-preview" role="status">{{ session('profile_picture_status') }}</div>
@endif
<form method="POST" action="{{ route('settings.profile-picture') }}" enctype="multipart/form-data" data-picture-form>@csrf
    <div class="hope-actions">
        <span class="hope-avatar" id="profile-initial" @if($currentPicture) hidden @endif>{{ mb_substr(auth()->user()->fname ?? 'U', 0, 1) }}</span>
        <img id="profile-photo-preview" class="hope-avatar" style="object-fit:cover" alt="Your profile photo" @if($currentPicture) src="{{ $currentPicture }}" @else hidden @endif><div>
            <label for="profile-photo" class="hope-text-button">Choose a photo</label>
            <input id="profile-photo" name="profile_picture" type="file" accept=".jpg,.jpeg,.png" data-profile-photo data-validate-file data-max-mb="5">
            <p>JPG or PNG, up to 5 MB.</p>
            <button class="hope-button" type="submit" id="save-photo" disabled>Save photo</button>
        </div>
        </div>
        <p class="hope-error" id="photo-error" role="alert">{{ $errors->first('profile_picture') }}</p>
    </form>
</section>

<section class="hope-card">
    <h2>Preferences</h2>
    <p>Changes save as soon as you switch them.</p>
    <form method="POST" action="{{ route('settings.update') }}" data-preferences-form>@csrf
        <div class="hope-toggle-row">
            <label for="email-preference">Email notifications<small>Sign-in asks for a 6-digit code sent to your email, and account activity is emailed to you.</small></label>
            <input type="hidden" name="email_notifications" value="0">
            <input id="email-preference" type="checkbox" role="switch" class="hope-switch" name="email_notifications" value="1" data-preference="email_notifications" @checked(filter_var(Auth::user()->email_notifications ?? true, FILTER_VALIDATE_BOOLEAN))>
        </div>
        <div class="hope-toggle-row">
            <label for="dark-preference">Dark mode<small>A softer view across the app.</small></label>
            <input type="hidden" name="dark_mode" value="0">
            <input id="dark-preference" type="checkbox" role="switch" class="hope-switch" name="dark_mode" value="1" data-preference="dark_mode" @checked(filter_var(Auth::user()->dark_mode ?? false, FILTER_VALIDATE_BOOLEAN))>
        </div>
        <noscript><div class="hope-actions"><button type="submit" class="hope-button">Save preferences</button></div></noscript>
        <p class="hope-pref-status" data-preference-status role="status" aria-live="polite"></p>
    </form>
</section>
</div>

<section class="hope-card hope-section">
    <h2>Account</h2>
    <p>Your details on file. Update your name and phone number on the account details page.</p>
    <dl class="hope-detail">
        @foreach(['fname' => 'First name', 'lname' => 'Last name', 'email' => 'Email address', 'phone' => 'Phone number', 'address' => 'Address', 'date_of_birth' => 'Birthdate'] as $field => $label)
            <div><dt>{{ $label }}</dt><dd>{{ auth()->user()->$field ?: 'Not provided' }}</dd></div>
        @endforeach
    </dl>
    <div class="hope-actions">
        <a class="hope-button" href="{{ route('user.edit') }}">Edit account details</a>
        <a class="hope-button secondary" href="{{ route('change-password.form') }}">Change password</a>
        <span class="hope-muted-note">Your password is never displayed.</span>
    </div>
</section>

<dialog class="hope-dialog" data-preference-dialog data-email="{{ auth()->user()->email }}" aria-labelledby="preference-dialog-title">
    <h2 id="preference-dialog-title" data-preference-title>Change email verification?</h2>
    <p data-preference-text></p>
    <div class="hope-actions"><button type="button" class="hope-button secondary" data-preference-cancel>Cancel</button><button type="button" class="hope-button" data-preference-confirm>Confirm</button></div>
</dialog>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('profile-photo');
    var save = document.getElementById('save-photo');
    var preview = document.getElementById('profile-photo-preview');
    var initial = document.getElementById('profile-initial');
    var error = document.getElementById('photo-error');
    if (!input || !save) return;
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        save.disabled = true;
        if (!file) return;
        var okType = ['image/jpeg', 'image/png'].indexOf(file.type) !== -1;
        var okSize = file.size <= 5 * 1024 * 1024;
        if (!okType || !okSize) {
            if (error) error.textContent = !okType ? 'Please choose a JPG or PNG image.' : 'That photo is larger than 5 MB.';
            input.value = '';
            return;
        }
        if (error) error.textContent = '';
        if (preview) { preview.src = URL.createObjectURL(file); preview.hidden = false; }
        if (initial) initial.hidden = true;
        save.disabled = false;
    });
});
</script>
@endsection