{{--
    Guided tour for new users (config/tour.php). It starts by itself on the role's home page until
    the person finishes or skips it once, and again whenever the page is opened with ?tour=1
    (Help & tour → "Start the tour").
--}}
@php
    $tourRole = \App\Http\Middleware\EnsureRole::resolveRole(auth()->user()) ?? 'user';
    $tourHome = \App\Http\Middleware\EnsureRole::homeRouteFor($tourRole);
    $tourSeen = (int) (auth()->user()->tour_version ?? 0) >= (int) config('tour.version');
    $tourConfig = [
        'steps' => config("tour.steps.{$tourRole}", []),
        'autostart' => request()->routeIs($tourHome) && (! $tourSeen || request()->boolean('tour')),
        'completeUrl' => route('tour.complete'),
        'helpUrl' => route('help'),
    ];
@endphp
<script>window.giftOfHopeTour = @json($tourConfig);</script>
