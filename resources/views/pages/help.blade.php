@extends(\App\Support\Layout::forRole())
@section('title', 'Help & tour - Gift of Hope')
@section('page-kicker', 'Help')
@section('page-title', 'Help & tour')
@section('content')
@php
    $limits = config('funding.beneficiaries');
    $assessmentDays = config('funding.assessment_days');
    $liquidationDays = config('funding.liquidation_days');
    // The whole process; "who" says which role does the step, so each reader sees their part.
    $process = [
        ['user', 'The requester submits a fund request', "Organization, address, the kinds of help needed, how many people ({$limits['min']}–{$limits['max']}), where to receive the funds, a formal letter and a Certificate of Indigency. The request is then \"Under assessment\"."],
        ['admin', 'A social worker interviews and assesses', "Within {$assessmentDays} days. The admin schedules the interview (the requester confirms or asks for another time), verifies the documents and records the social worker's findings, with the amount the request needs."],
        ['admin', 'The admin recommends a decision', 'Approval (with an amount, at most what the assessment says is needed) or rejection, with verification notes for the super admin.'],
        ['super_admin', 'The super admin gives the final approval', 'After checking everything about the request: the requester, documents, assessment and amount needed, notes and the AI assessment. The requester is notified either way.'],
        ['user', 'The requester lists the people', 'With "Add person": the last, first and middle name of each person who will receive the help.'],
        ['admin', 'The funds are sent', "To the bank account or e-wallet in the receiver's name. An admin records the transfer with its proof, or the super admin sends it automatically once transfers are connected."],
        ['user', 'The requester reports how the money was used', "Within {$liquidationDays} days: receipts and the list of people who received the help. An admin checks it and the request is completed."],
    ];
    $roleNames = ['user' => 'Requester', 'admin' => 'Admin', 'super_admin' => 'Super admin'];
    $guides = [
        'user' => [
            'Start a request from the home feed or "Fund request". You can open the six steps in any order; everything that is missing is listed before you submit.',
            'Follow each request in "Request status". Open one to see its progress, answer the interview schedule, upload a clearer document, or message the foundation.',
            'After approval, list the people who will receive the help. After the funds arrive, submit the liquidation.',
            'A rejected request can be appealed up to ' . \App\Services\FundingService::MAX_APPEALS . ' times, with a reason and an updated document.',
            'Changed your mind? Cancel the request from its page any time before the funds are sent; everything still to come stops.',
            'Turn on sign-in codes by email, or dark mode, in Settings.',
        ],
        'admin' => [
            'Open "Funding" and a request to schedule the interview, record the assessment with the amount needed, review the documents and add verification notes.',
            'Approval needs: the social worker recommends it, every required document is verified, and the amount needed is recorded. Your decision goes to the super admin.',
            'After approval, wait for the list of people (you get a notification), then send the funds and record the release with the proof.',
            'Check each liquidation: verify it, or return it with remarks.',
            'Post updates (with photos) in "Updates"; see reports in "Reports" and the donation boxes in "IoT box monitor".',
        ],
        'super_admin' => [
            'Requests waiting for you are on the dashboard and in "Fund requests". Open one for the final check and approve (set the amount) or reject (give the reason).',
            'When automatic transfers are connected, send the approved amount from the request page; you confirm with a code sent to your email.',
            'Manage accounts (roles, access) and System settings (scoring priorities, request interval, Brevo email).',
            'System monitor and Activity log show the donation boxes, funding outcomes and every change made in the system.',
        ],
    ];
    $faq = [
        'user' => [
            ['Why was my account name refused?', 'Funds are only sent to an account in the receiver\'s name. The account name must match the organization name or the contact person exactly.'],
            ['How long does it take?', "A social worker contacts you within {$assessmentDays} days. After the assessment the admin and the super admin decide; you get a notification at every step."],
            ['Why do I list the people only after approval?', 'The approval says how many people the help covers, so the list is made once that number is final.'],
            ['Can I cancel a request?', 'Yes, from the request page, until the funds are sent. The interview, the decision and the release of funds all stop. You can send a new request later.'],
            ['I did not get the email code.', 'Check your spam folder. If email is not set up yet, you can sign in without a code.'],
        ],
        'admin' => [
            ['Why can\'t I approve a request?', 'The social worker must recommend it, every required document must be verified, and the amount needed must be recorded in the assessment.'],
            ['How is the amount decided?', 'By the admin / social worker after the interview, from what the people need. Record it, and how it was decided, in the assessment; you can change it until you send your decision.'],
            ['When can I record the release?', 'After the super admin approves the request and the requester has listed the people who will receive the help.'],
        ],
        'super_admin' => [
            ['Why can\'t I send a transfer?', 'The request must be approved, its list of people must be in, the foundation\'s funds must cover every approved amount, and the bank or e-wallet must have a payout channel. Until a payout provider is connected, admins send the funds and record the release.'],
            ['Why are emails not sent?', 'Save the Brevo API key (it starts with "xkeysib-") and a verified sender in System settings, then use "Send a test email".'],
        ],
    ][$role] ?? [];
@endphp
<div class="hope-page">
    <div class="hope-heading">
        <div>
            <div class="hope-eyebrow">HELP &amp; TOUR</div>
            <h1>How Gift of Hope works</h1>
            <p>Try each page with a short tutorial, or read how a request goes from start to finish.</p>
        </div>
    </div>

    <section class="hope-card" aria-labelledby="tutorials-title">
        <h2 id="tutorials-title">Tutorials</h2>
        <p>Short and hands-on: each one shows a page and lets you try it. The <b>?</b> at the top of a page starts its tutorial too.</p>
        <ul class="help-tutorials">
            @foreach ($tutorials as $tutorial)
                <li>
                    <div><strong>{{ $tutorial['title'] }}@if ($tutorial['done'])<span class="help-done">✓ Done</span>@endif</strong><small>{{ $tutorial['about'] }}</small></div>
                    @if ($tutorial['url'])
                        <a class="hope-button secondary" href="{{ $tutorial['url'] }}" aria-label="Start: {{ $tutorial['title'] }}">Start</a>
                    @else
                        <small class="help-later">After the first request</small>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    <section class="hope-card hope-section">
        <h2>The steps of a fund request</h2>
        <p>Your part is highlighted.</p>
        <ol class="help-steps">
            @foreach ($process as [$who, $title, $text])
                <li class="{{ $who === $role ? 'is-you' : '' }}"><div><strong>{{ $title }}</strong><p>{{ $text }}</p><span class="help-who">{{ $roleNames[$who] }}</span></div></li>
            @endforeach
        </ol>
    </section>

    <div class="hope-grid two hope-section">
        <section class="hope-card">
            <h2>What you do</h2>
            <ul class="help-list">@foreach ($guides[$role] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul>
        </section>
        <section class="hope-card help-faq">
            <h2>Questions</h2>
            @foreach ($faq as [$question, $answer])
                <details><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>
            @endforeach
        </section>
    </div>
</div>
@endsection
