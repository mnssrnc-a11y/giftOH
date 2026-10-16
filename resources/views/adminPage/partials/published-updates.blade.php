{{--
    The admin's published updates. Up to three are listed; more than three become a slideshow
    (one update at a time, moved with the arrows and dots). Each one can be edited or deleted.
    @param array    $posts      AdminDashboardService post rows, newest first
    @param array    $postTypes  type => label
    @param \Closure $when       date → "2 days ago"
--}}
@php($postCount = count($posts))
<div class="admin-card-body admin-post-list {{ $postCount > 3 ? 'is-carousel' : '' }}">
    @if ($postCount > 3)
        <div class="hope-carousel is-sliding admin-post-carousel" data-carousel data-carousel-manual aria-roledescription="carousel" aria-label="Published updates">
            <div class="hope-carousel-viewport">
                <div class="hope-carousel-track" data-carousel-track aria-live="polite">
                    @foreach ($posts as $index => $post)
                        <div class="admin-post-slide" data-carousel-slide role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} of {{ $postCount }}">
                            @include('adminPage.partials.post-item')
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="hope-carousel-controls">
                <button type="button" class="hope-carousel-arrow" data-carousel-prev aria-label="Previous update">‹</button>
                <div class="hope-carousel-dots">
                    @foreach ($posts as $index => $post)
                        <button type="button" data-carousel-dot="{{ $index }}" aria-label="Show update {{ $index + 1 }} of {{ $postCount }}: {{ $post['title'] }}" @if ($index === 0) aria-current="true" @endif></button>
                    @endforeach
                </div>
                <button type="button" class="hope-carousel-arrow" data-carousel-next aria-label="Next update">›</button>
                <span class="admin-post-count" data-carousel-count>1 of {{ $postCount }}</span>
            </div>
        </div>
    @else
        @forelse ($posts as $post)
            @include('adminPage.partials.post-item')
        @empty
            <div class="admin-empty admin-state-visible"><strong>No updates posted yet</strong><p>Your first update will appear here, on user pages, and (if public) on the landing page.</p></div>
        @endforelse
    @endif
</div>
