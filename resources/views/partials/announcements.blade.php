{{--
    Foundation announcements. One post shows as a card; two or more become a slideshow that
    moves to the next announcement every few seconds and loops. Hovering, focusing or the pause
    button stops it. Each post may carry a photo.
    @param array $posts  newest first (admin_posts records)
--}}
@php
    $slides = array_values($posts ?? []);
    $total = count($slides);
@endphp
@if ($total === 0)
    <p class="hope-empty">No updates yet. Announcements from the foundation will appear here.</p>
@else
<div class="hope-carousel {{ $total > 1 ? 'is-sliding' : '' }}" @if ($total > 1) data-carousel @endif aria-roledescription="carousel" aria-label="Announcements from Gift of Hope">
    <div class="hope-carousel-viewport">
        <div class="hope-carousel-track" data-carousel-track aria-live="off">
            @foreach ($slides as $index => $post)
                @php($image = \App\Repositories\FirebaseAdminPostRepository::imageUrl($post))
                <article class="hope-slide {{ $image ? 'has-image' : '' }}" data-carousel-slide @if ($total > 1) role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} of {{ $total }}" @endif>
                    @if ($image)<img class="hope-slide-image" src="{{ $image }}" alt="{{ $post['title'] ?? 'Announcement photo' }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}">@endif
                    <div class="hope-slide-body">
                        <div class="hope-slide-meta">
                            <span class="hope-badge">{{ ucwords(str_replace('_', ' ', $post['type'] ?? 'announcement')) }}</span>
                            @if (! empty($post['created_at']))<time datetime="{{ $post['created_at'] }}">{{ \Carbon\Carbon::parse($post['created_at'])->format('M d, Y') }}</time>@endif
                        </div>
                        <h3>{{ $post['title'] ?? 'Update' }}</h3>
                        <p>{{ \Illuminate\Support\Str::limit($post['body'] ?? '', 600) }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
    @if ($total > 1)
        <div class="hope-carousel-controls">
            <button type="button" class="hope-carousel-arrow" data-carousel-prev aria-label="Previous announcement">‹</button>
            <div class="hope-carousel-dots">
                @foreach ($slides as $index => $post)
                    <button type="button" data-carousel-dot="{{ $index }}" aria-label="Show announcement {{ $index + 1 }} of {{ $total }}" @if ($index === 0) aria-current="true" @endif></button>
                @endforeach
            </div>
            <button type="button" class="hope-carousel-arrow" data-carousel-pause aria-label="Pause the slideshow" aria-pressed="false">❚❚</button>
            <button type="button" class="hope-carousel-arrow" data-carousel-next aria-label="Next announcement">›</button>
        </div>
    @endif
</div>
@endif
