{{--
    Barangay of a Pampanga address (config/pampanga.php), grouped by city / municipality.
    resources/js/places.js keeps only the barangays of the town chosen in [data-place-town].
    @param string $name   field name (also the id)
    @param ?string $town  the town chosen before (old input), to keep its barangay selected
    @param string $extra  extra attributes (aria-describedby, classes…)
--}}
<select id="{{ $name }}" name="{{ $name }}" required data-place-barangay {!! $extra ?? '' !!}>
    <option value="">Choose the barangay…</option>
    @foreach (config('pampanga') as $townName => $barangays)
        <optgroup label="{{ $townName }}">
            @foreach ($barangays as $barangay)<option @selected($townName === ($town ?? null) && old($name) === $barangay)>{{ $barangay }}</option>@endforeach
        </optgroup>
    @endforeach
</select>
