{{--
    Associated with SACOP slideshow (landing page), from config/sacop.php. Each slide shows a photo
    (posted on sacop.ph, or one of ours for the parishes; a plain card when there is none or it
    cannot load), what the organization is, where, how it is connected with SACOP, and a link to
    the SACOP page.
    Uses the announcement slideshow (resources/js/announcements.js).
--}}
@php
    $organizations = \App\Support\SacopAssociated::all();
    $total = count($organizations);
    $initials = fn (string $name) => collect(preg_split('/\s+/', trim(preg_replace('/\(.*\)/', '', $name))))
        ->reject(fn ($word) => in_array(mb_strtolower($word), ['the', 'of', 'del', 'sto.', 'sacop'], true))
        ->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join('');
@endphp
<div class="hope-carousel is-sliding assoc-carousel" data-carousel aria-roledescription="carousel" aria-label="Organizations associated with SACOP">
    <div class="hope-carousel-viewport">
        <div class="hope-carousel-track" data-carousel-track aria-live="off">
            @foreach ($organizations as $index => $org)
                <article class="hope-slide has-image assoc-slide" data-carousel-slide role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} of {{ $total }}: {{ $org['name'] }}">
                    <div class="assoc-media">
                        <div class="assoc-art" aria-hidden="true"><span>{{ $initials($org['name']) }}</span><small>{{ $org['kind'] }}</small></div>
                        @if ($org['photo'])
                            <img src="{{ \App\Support\SacopAssociated::photoUrl($org) }}" alt="{{ $org['alt'] ?? \Illuminate\Support\Str::after($org['credit'], 'Photo: ') }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" referrerpolicy="no-referrer" data-slide-photo>
                        @endif
                    </div>
                    <div class="hope-slide-body">
                        <div class="hope-slide-meta">
                            <span class="hope-badge">{{ $org['kind'] }}</span>
                            <span class="assoc-check">✓ Associated with SACOP</span>
                        </div>
                        <h3>{{ $org['name'] }}</h3>
                        @if ($org['location'])<p class="assoc-place">{{ $org['location'] }}</p>@endif
                        <p>{{ $org['connection'] }}</p>
                        <a class="assoc-source" href="{{ $org['source'] }}" target="_blank" rel="noopener">See it on sacop.ph ↗</a>
                        @if ($org['credit'])<small class="assoc-credit">{{ $org['credit'] }}</small>@endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
    <div class="hope-carousel-controls">
        <button type="button" class="hope-carousel-arrow" data-carousel-prev aria-label="Previous organization">‹</button>
        <div class="hope-carousel-dots">
            @foreach ($organizations as $index => $org)
                <button type="button" data-carousel-dot="{{ $index }}" aria-label="Show {{ $org['name'] }} ({{ $index + 1 }} of {{ $total }})" @if ($index === 0) aria-current="true" @endif></button>
            @endforeach
        </div>
        <button type="button" class="hope-carousel-arrow" data-carousel-pause aria-label="Pause the slideshow" aria-pressed="false">❚❚</button>
        <button type="button" class="hope-carousel-arrow" data-carousel-next aria-label="Next organization">›</button>
    </div>
</div>
