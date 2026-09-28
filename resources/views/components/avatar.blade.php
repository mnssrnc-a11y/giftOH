{{-- Profile photo from the user's profile_picture field, falling back to their initial. --}}
@props(['user' => auth()->user(), 'tag' => 'span', 'fallback' => 'U'])
@php
    $photoUrl = $user && method_exists($user, 'profilePhotoUrl') ? $user->profilePhotoUrl() : null;
    $initial = mb_strtoupper(mb_substr($user->fname ?? $user->name ?? $fallback, 0, 1));
@endphp
<{{ $tag }} {{ $attributes->merge(['style' => $photoUrl ? 'overflow:hidden;padding:0' : '']) }}>@if ($photoUrl)<img src="{{ $photoUrl }}" alt="" style="display:block;width:100%;height:100%;object-fit:cover;border-radius:inherit">@else{{ $initial }}@endif</{{ $tag }}>
