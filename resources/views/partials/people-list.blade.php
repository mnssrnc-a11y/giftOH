{{--
    "Add person" list: each row is one person (last, first and middle name).
    @param string $name    form field name ("people" → people[0][last], people[0][first], ...)
    @param array  $people  rows to start with: [['last' => .., 'first' => .., 'middle' => ..], ...]
    @param int    $limit   most people allowed
    @param bool   $required whether last and first name are required
--}}
@php
    $required = $required ?? true;
    $rows = array_values($people ?? []) ?: [['last' => '', 'first' => '', 'middle' => '']];
    $row = fn ($index, array $person = []) => '<li class="people-row" data-people-row>'
        . '<input data-part="last" name="' . e($name) . '[' . $index . '][last]" value="' . e($person['last'] ?? '') . '" placeholder="Last name" aria-label="Last name, person ' . (is_int($index) ? $index + 1 : '') . '" maxlength="80" autocomplete="off"' . ($required ? ' required' : '') . '>'
        . '<input data-part="first" name="' . e($name) . '[' . $index . '][first]" value="' . e($person['first'] ?? '') . '" placeholder="First name" aria-label="First name, person ' . (is_int($index) ? $index + 1 : '') . '" maxlength="80" autocomplete="off"' . ($required ? ' required' : '') . '>'
        . '<input data-part="middle" name="' . e($name) . '[' . $index . '][middle]" value="' . e($person['middle'] ?? '') . '" placeholder="Middle name (optional)" aria-label="Middle name, person ' . (is_int($index) ? $index + 1 : '') . '" maxlength="80" autocomplete="off">'
        . '<button type="button" class="people-remove" data-people-remove aria-label="Remove this person">×</button></li>';
@endphp
<div class="people" data-people data-name="{{ $name }}" data-limit="{{ $limit }}">
    <ol class="people-list" data-people-list>
        @foreach ($rows as $index => $person){!! $row($index, (array) $person) !!}@endforeach
    </ol>
    <template data-people-template>{!! $row('__INDEX__') !!}</template>
    <div class="people-tools">
        <button type="button" class="people-add" data-people-add>＋ Add person</button>
        <span class="people-count" data-people-count aria-live="polite"></span>
    </div>
</div>
