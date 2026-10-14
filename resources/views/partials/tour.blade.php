{{--
    Hands-on tutorials of this page (config/tour.php). Each starts by itself the first time its page
    or section is opened, and again from the "?" button, ?tutorial=<id> (Help & tour) or ?tour=1.
--}}
@php
    $tutorialRole = \App\Http\Middleware\EnsureRole::resolveRole(auth()->user()) ?? 'user';
    $pageTutorials = collect(\App\Support\Tutorials::forCurrentPage($tutorialRole));
    $askedFor = request()->boolean('tour') ? \App\Support\Tutorials::welcomeFor($tutorialRole) : request()->query('tutorial');
    $tourConfig = [
        'tutorials' => $pageTutorials->map(fn ($t) => ['id' => $t['id'], 'section' => $t['section'], 'title' => $t['title'], 'steps' => $t['steps']])->all(),
        'seen' => \App\Support\Tutorials::seen(auth()->user()),
        'start' => $pageTutorials->contains('id', $askedFor) ? $askedFor : null,
        'completeUrl' => route('tour.complete'),
    ];
@endphp
<script>window.giftOfHopeTour = @json($tourConfig);</script>
