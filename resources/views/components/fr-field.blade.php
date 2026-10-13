{{--
    One field of the fund request form: label (with * when required), the input (or the control
    passed in the slot), an optional hint, and the error message for this field.
--}}
@props(['name', 'label', 'type' => 'text', 'required' => true, 'hint' => null, 'value' => null, 'wide' => false])
@php($error = $errors->first($name) ?: $errors->first($name . '.*'))
<div class="fr-field {{ $wide ? 'is-wide' : '' }} {{ $error ? 'has-error' : '' }}" data-field="{{ $name }}">
    <label for="{{ $name }}" id="{{ $name }}-label">{{ $label }}@if ($required)<span class="fr-req" aria-hidden="true"> *</span><span class="sr-only"> (required)</span>@else<span class="fr-optional"> (optional)</span>@endif</label>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @if ($required) required @endif
            aria-describedby="{{ $name }}-error{{ $hint ? " {$name}-hint" : '' }}" @if ($error) aria-invalid="true" @endif {{ $attributes }}>
    @endif
    @if ($hint)<small id="{{ $name }}-hint">{{ $hint }}</small>@endif
    <p class="fr-error" id="{{ $name }}-error" data-error-for="{{ $name }}">{{ $error }}</p>
</div>
