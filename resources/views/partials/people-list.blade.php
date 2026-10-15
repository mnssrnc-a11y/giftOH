{{--
    "Add person" list: each row is one person (last, first and middle name). People with alike names
    (two "Juan Dela Cruz") also get a "No." box, and each must be given a different number.
    @param string $name    form field name ("people" → people[0][last], people[0][first], ...)
    @param array  $people  rows to start with: [['last' => .., 'first' => .., 'middle' => .., 'number' => ..], ...]
    @param int    $limit   most people allowed
    @param bool   $required whether last and first name are required
--}}
@php
    $required = $required ?? true;
    $rows = array_values($people ?? []) ?: [['last' => '', 'first' => '', 'middle' => '']];
    $rules = app(\App\Services\FundingRules::class);
    $alike = $rules->similarNameGroups($rows);
    $numbered = array_merge([], ...$alike);
    $row = fn ($index, array $person = []) => '<li class="people-row' . (in_array($index, $numbered, true) ? ' needs-number' : '') . '" data-people-row>'
        . '<input data-part="last" name="' . e($name) . '[' . $index . '][last]" value="' . e($person['last'] ?? '') . '" placeholder="Last name" aria-label="Last name, person ' . (is_int($index) ? $index + 1 : '') . '" maxlength="80" autocomplete="off"' . ($required ? ' required' : '') . '>'
        . '<input data-part="first" name="' . e($name) . '[' . $index . '][first]" value="' . e($person['first'] ?? '') . '" placeholder="First name" aria-label="First name, person ' . (is_int($index) ? $index + 1 : '') . '" maxlength="80" autocomplete="off"' . ($required ? ' required' : '') . '>'
        . '<input data-part="middle" name="' . e($name) . '[' . $index . '][middle]" value="' . e($person['middle'] ?? '') . '" placeholder="Middle name (optional)" aria-label="Middle name, person ' . (is_int($index) ? $index + 1 : '') . '" maxlength="80" autocomplete="off">'
        . '<input data-part="number" type="number" inputmode="numeric" min="1" max="99" name="' . e($name) . '[' . $index . '][number]" value="' . e($person['number'] ?? '') . '" placeholder="No." aria-label="Number for person ' . (is_int($index) ? $index + 1 : '') . ' (similar name)"' . (in_array($index, $numbered, true) ? ' required' : '') . '>'
        . '<button type="button" class="people-remove" data-people-remove aria-label="Remove this person">×</button></li>';
    $positions = fn (array $group) => implode(', ', array_map(fn ($index) => '#' . ($index + 1), array_slice($group, 0, -1))) . ' and #' . (end($group) + 1);
@endphp
<div class="people" data-people data-name="{{ $name }}" data-limit="{{ $limit }}">
    <ol class="people-list {{ $alike ? 'has-numbers' : '' }}" data-people-list>
        @foreach ($rows as $index => $person){!! $row($index, (array) $person) !!}@endforeach
    </ol>
    <template data-people-template>{!! $row('__INDEX__') !!}</template>
    <div class="people-alike" data-people-alike role="status" @if (! $alike) hidden @endif>
        <strong>Similar names:</strong>
        <span data-people-alike-text>@foreach ($alike as $group)persons {{ $positions($group) }} ({{ $rules->personName(['first' => $rows[$group[0]]['first'] ?? '', 'last' => $rows[$group[0]]['last'] ?? '']) }})@if (! $loop->last); @endif @endforeach</span>.
        Enter a number for each in the <strong>No.</strong> box (1, 2, 3…) so the foundation can tell them apart.
    </div>
    <div class="people-tools">
        <button type="button" class="people-add" data-people-add>＋ Add person</button>
        <span class="people-count" data-people-count aria-live="polite"></span>
    </div>
</div>
