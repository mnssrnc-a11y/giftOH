@extends(\App\Support\Layout::forRole())
@section('title', 'Settings - Gift of Hope')
@section('content')
<div class="hope-actions" style="margin:24px">
    <a class="hope-button secondary" href="{{ route('user.edit') }}">Edit account details</a>
    <a class="hope-button secondary" href="{{ route('change-password.form') }}">Change account password</a>
</div>
<div class="hope-page">
    <div class="hope-heading"><div>
    <div class="hope-eyebrow">SETTINGS</div>
    <h1>Make yourself at home.</h1>
    <p>Manage your profile, preferences, and account security.</p>
    </div>
</div>
<div class="hope-grid two">
    <section class="hope-card">
        <h2>Profile picture</h2>
        <p>Give your community a familiar face.</p>
@php($currentPicture = auth()->user()->profile_picture)
@if(session('profile_picture_status'))
<div class="hope-preview" role="status">{{ session('profile_picture_status') }}</div>
@endif
<form method="POST" action="{{ route('settings.profile-picture') }}" enctype="multipart/form-data" data-picture-form>@csrf
    <div class="hope-actions">
        <span class="hope-avatar" id="profile-initial" @if($currentPicture) hidden @endif>{{ mb_substr(auth()->user()->fname ?? 'U', 0, 1) }}</span>
        <img id="profile-photo-preview" class="hope-avatar" style="object-fit:cover" alt="Your profile photo" @if($currentPicture) src="{{ asset('storage/'.$currentPicture) }}" @else hidden @endif><div>
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
    <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">@csrf
        <div class="hope-toggle-row">
            <label for="email-preference">Email notifications<small>Receive account activity emails, including sign-in and verification codes.</small></label>
            <input id="email-preference" type="checkbox" name="email_notifications" value="1" class="w-5 h-5" {{ filter_var(Auth::user()->email_notifications ?? true, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}/>
        </div>
        <div class="hope-toggle-row">
            <label for="dark-preference">Dark mode<small>A softer view across the app.</small></label>
            <input id="dark-preference" type="checkbox" name="dark_mode" value="1" class="w-5 h-5" {{ filter_var(Auth::user()->dark_mode ?? false, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}/>
        </div>
        <button type="submit" class="hope-button">Save preferences</button>
        @if (session('status'))
            <p class="text-sm text-green-700 mt-4">{{ session('status') }}</p>
        @endif
    </form>
</section>
</div>

<section class="hope-card hope-section">
    <h2>Personal information</h2>
    <p>Your current details on file. Changes are made on the account details page and verified through your current email address.</p>
    <dl class="hope-detail">
        @foreach(['fname' => 'First name', 'lname' => 'Last name', 'email' => 'Email address', 'phone' => 'Phone number', 'address' => 'Address', 'date_of_birth' => 'Birthdate'] as $field => $label)
            <div><dt>{{ $label }}</dt><dd>{{ auth()->user()->$field ?: 'Not provided' }}</dd></div>
        @endforeach
    </dl>
    <div class="hope-actions">
        <a class="hope-button" href="{{ route('user.edit') }}">Edit account details</a>
    </div>
</section>

<section class="hope-card hope-section">
    <div class="hope-heading" style="margin:0">
        <div>
            <h2>Security</h2>
            <p>Password protected · Your password is never displayed.</p>
        </div>
        <a class="hope-button secondary" href="{{ route('change-password.form') }}">Change password</a>
    </div>
</section>
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