@extends('layouts.dashboard')

@section('title', 'Fund Request Details - Gift of Hope')

@section('content')
@php
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
    $day = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('M d, Y') : '—';
    $stage = $view['stage'];
    // The progress bar the requester sees (it reaches "Admin review" once the admin is verifying).
    $progress = $view['progress'];
    $assessment = $view['assessment'];
    $disbursement = $view['disbursement'];
    $liquidation = $view['liquidation'];
    $canLiquidate = in_array($stage['key'], ['released', 'liquidation_returned'], true);
    $badge = match (true) {
        in_array($stage['key'], ['completed', 'awaiting_recipients', 'to_release', 'released'], true) => 'bg-green-100 text-green-800',
        in_array($stage['key'], ['rejected', 'cancelled'], true) => 'bg-red-100 text-red-800',
        in_array($stage['key'], ['liquidation_returned'], true) => 'bg-amber-100 text-amber-800',
        default => 'bg-blue-100 text-blue-800',
    };
    $card = 'hope-card';
    $appealsLeft = \App\Services\FundingService::MAX_APPEALS - (int) ($fundRequest['appeals'] ?? 0);
@endphp

<div class="hope-page">
    <div class="hope-heading">
        <div>
            <div class="hope-eyebrow">FUND REQUEST</div>
            <h1>{{ $view['organization'] }}</h1>
            <p>{{ $view['category'] }} · Request {{ \Illuminate\Support\Str::limit($view['id'], 8, '') }} · submitted {{ $day($view['created_at']) }}</p>
        </div>
        <a href="{{ route('request-status') }}" class="hope-button secondary">← All my requests</a>
    </div>

    <div class="space-y-6">
        @if (session('success'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('success') }}</div>@endif
        @if (session('alert_error'))<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ session('alert_error') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        {{-- Progress --}}
        <section class="{{ $card }}" id="progress">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-gray-900">Progress</h2>
                <span class="rounded-full px-4 py-1.5 text-sm font-semibold {{ $badge }}">{{ $progress['label'] }}</span>
            </div>
            @if ($stage['key'] === 'rejected')
                <p class="rounded-lg bg-red-50 p-4 text-sm text-red-800"><strong>Reason:</strong> {{ $view['rejection_reason'] ?? 'No reason was given.' }}</p>
                @if ($appealsLeft > 0)
                    <form method="POST" action="{{ route('fund-request.appeal', $view['id']) }}" enctype="multipart/form-data" class="mt-5 space-y-3 rounded-lg border border-gray-200 p-4">
                        @csrf
                        <h3 class="text-base font-bold text-gray-900">Appeal this decision</h3>
                        <p class="text-sm text-gray-600">Explain what has changed and attach an updated supporting document. The foundation reviews the request again. You have {{ $appealsLeft }} appeal(s) left.</p>
                        <textarea name="reason" required maxlength="2000" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="What is different now?">{{ old('reason') }}</textarea>
                        <input type="file" name="document" required accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-sm">
                        <button type="submit" class="rounded-lg bg-blue-700 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-800">Send appeal</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-gray-600">This request has been appealed {{ \App\Services\FundingService::MAX_APPEALS }} times, the most allowed.</p>
                @endif
            @elseif ($stage['key'] === 'cancelled')
                <p class="rounded-lg bg-red-50 p-4 text-sm text-red-800">You cancelled this request on {{ $day($view['cancelled']['at'] ?? null) }}. The interview, the decision and the release of funds were all stopped.@if (! empty($view['cancelled']['reason'])) <br><strong>Your reason:</strong> {{ $view['cancelled']['reason'] }}@endif</p>
            @else
                @php($stepTargets = ['#details', '#progress', '#progress', '#progress', '#release', '#liquidation', '#history'])
                <ol class="grid grid-cols-2 gap-3 md:grid-cols-7">
                    @foreach (\App\Services\FundingRequestPresenter::STEPS as $i => $step)
                        @php($state = $i < $progress['step'] || $stage['key'] === 'completed' ? 'done' : ($i === $progress['step'] ? 'current' : 'todo'))
                        <li class="text-xs"><a href="{{ $stepTargets[$i] }}" class="block rounded-md p-1 hover:bg-blue-50" @if ($state === 'current') aria-current="step" @endif><span class="mb-1 block h-1.5 rounded-full {{ $state === 'done' ? 'bg-green-500' : ($state === 'current' ? 'bg-[#1976D2]' : 'bg-gray-200') }}"></span><span class="{{ $state === 'todo' ? 'text-gray-400' : 'font-semibold text-gray-800' }}">{{ $step }}</span></a></li>
                    @endforeach
                </ol>
            @endif

            @if ($stage['key'] === 'needs_assessment')
                <p class="mt-5 text-sm text-gray-600">A social worker will contact you to schedule an interview and assessment by <strong>{{ $day($view['assessment_due']) }}</strong>.</p>
            @elseif (!empty($assessment['interview_at']) && $stage['step'] <= 1)
                <div class="mt-5 rounded-lg bg-blue-50 p-4 text-sm text-blue-900" data-tour="interview">
                    <strong>Interview:</strong> {{ \Carbon\Carbon::parse($assessment['interview_at'])->format('l, M d, Y g:i A') }}
                    · {{ $assessment['mode_label'] ?? '' }}@if (!empty($assessment['location'])) · {{ $assessment['location'] }}@endif
                    @if (!empty($assessment['social_worker']))<br>Social worker: {{ $assessment['social_worker'] }}@endif
                    <br><span class="text-blue-700">Please have the original documents ready.</span>
                    @php($answer = $assessment['interview_response'] ?? null)
                    @if (! $answer)
                        <div class="mt-4 flex flex-wrap items-start gap-3">
                            <form method="POST" action="{{ route('fund-request.interview-response', $view['id']) }}">@csrf<input type="hidden" name="response" value="confirmed"><button class="rounded-lg bg-[#0D47A1] px-4 py-2 font-semibold text-white hover:bg-[#1565C0]">✓ I'll be there</button></form>
                            <details class="rounded-lg border border-blue-200 bg-white px-4 py-2 text-gray-800">
                                <summary class="cursor-pointer font-semibold text-blue-800">Ask for another schedule</summary>
                                <form method="POST" action="{{ route('fund-request.interview-response', $view['id']) }}" class="mt-3 grid gap-3 md:grid-cols-2">
                                    @csrf
                                    <input type="hidden" name="response" value="reschedule_requested">
                                    <label class="text-xs font-semibold text-gray-600">Date and time that works for you<input type="datetime-local" name="preferred_at" required min="{{ now()->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                                    <label class="text-xs font-semibold text-gray-600">Why the current schedule doesn't work<input name="note" required maxlength="1000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
                                    <div class="md:col-span-2"><button class="rounded-lg bg-[#0D47A1] px-4 py-2 font-semibold text-white">Send request</button></div>
                                </form>
                            </details>
                        </div>
                    @elseif ($answer['status'] === 'confirmed')
                        <p class="mt-3 font-semibold text-green-700">✓ You confirmed this interview.</p>
                    @else
                        <p class="mt-3 font-semibold text-amber-700">You asked for {{ \Carbon\Carbon::parse($answer['preferred_at'])->format('M d, Y g:i A') }}. The social worker will set a new schedule.</p>
                    @endif
                </div>
            @elseif ($stage['key'] === 'awaiting_recipients')
                <p class="mt-5 text-sm text-gray-600">Approved for <strong>{{ $peso($view['granted'] ?? $view['amount']) }}</strong>. Next: <a class="font-semibold text-blue-700 underline" href="#release">list the people who will receive the help</a>, then the foundation releases the funds.</p>
            @elseif ($stage['key'] === 'to_release')
                <p class="mt-5 text-sm text-gray-600">Approved for <strong>{{ $peso($view['granted'] ?? $view['amount']) }}</strong>. The foundation will release the funds by {{ strtolower($disbursement['method_label']) }}.</p>
            @endif
            @if (in_array($stage['key'], ['awaiting_recipients', 'to_release'], true) && ($line ?? null))
                {{-- First come, first served: donations go to the requests approved earlier first. --}}
                <div class="mt-3 rounded-lg {{ $line['covered'] ? 'bg-green-50 text-green-800' : 'bg-blue-50 text-blue-900' }} p-3 text-sm" id="funding-gap">
                    @if ($line['ready'])<p><strong>Your funds are ready.</strong> The foundation is sending them to your account.</p>
                    @elseif ($line['covered'])<p><strong>Your funds are set aside.</strong> They are sent once you list the people.</p>
                    @elseif (! $line['known'])<p><strong>You are #{{ $line['position'] }} in line.</strong> The donation total cannot be read right now; the amount still needed shows here once it can.</p>
                    @else
                        <p><strong>{{ $peso($line['short']) }} more in donations is needed</strong> before your transfer can be sent. You are #{{ $line['position'] }} in line.</p>
                        @php($percent = $line['amount'] > 0 ? (int) floor($line['raised'] / $line['amount'] * 100) : 0)
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-blue-100" role="progressbar" aria-label="Donations set aside for your request" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
                            <div class="h-full rounded-full bg-blue-600" style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="mt-1.5 text-xs">{{ $peso($line['raised']) }} of {{ $peso($line['amount']) }} set aside for you · {{ $peso($line['amount'] - $line['raised']) }} to go</p>
                        @if ($line['ahead_short'] > 0)
                            <p class="mt-1.5 text-xs">This includes {{ $peso($line['ahead_short']) }} still needed by requests approved before yours, which are funded first (first come, first served).</p>
                        @endif
                    @endif
                </div>
            @endif

            @if ($view['cancellable'])
                <details class="mt-5 rounded-lg border border-red-200 p-4 text-sm" id="cancel" @if ($errors->has('cancel_reason')) open @endif>
                    <summary class="cursor-pointer font-semibold text-red-700">Cancel this request</summary>
                    <form method="POST" action="{{ route('fund-request.cancel', $view['id']) }}" class="mt-3 space-y-3" data-cancel-request>
                        @csrf
                        <p class="text-gray-700">Cancelling stops everything still to come: the interview, the decision and the release of funds. It cannot be undone; you can send a new request later.</p>
                        <label class="block text-xs font-semibold text-gray-600">Why are you cancelling? <span class="font-normal">(optional)</span>
                            <textarea name="cancel_reason" maxlength="1000" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('cancel_reason') }}</textarea></label>
                        <label class="flex items-start gap-2 text-gray-800"><input type="checkbox" name="confirm" value="1" required class="mt-1"> I understand the request and its release of funds will be cancelled.</label>
                        <button type="submit" class="rounded-lg bg-red-700 px-5 py-2 font-semibold text-white hover:bg-red-800">Cancel request</button>
                    </form>
                </details>
            @endif
        </section>

        @if ($stage['key'] === 'awaiting_recipients')
            <section class="{{ $card }}" id="release">
                <h2 class="mb-1 text-lg font-bold text-gray-900">Release of funds: who will receive the help?</h2>
                <p class="mb-4 text-sm text-gray-600">Add each person with <strong>Add person</strong>: last name, first name and middle name. The approval covers <strong>{{ $view['people_limit'] }}</strong> {{ \Illuminate\Support\Str::plural('person', $view['people_limit']) }}. The funds are released once the list is in.</p>
                @error('people')<p class="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
                @if ($errors->has('people.*'))<ul class="mb-3 list-disc rounded-lg bg-red-50 p-3 pl-8 text-sm text-red-800" role="alert">@foreach ($errors->get('people.*') as $messages)<li>{{ $messages[0] }}</li>@endforeach</ul>@endif
                <form method="POST" action="{{ route('fund-request.beneficiaries', $view['id']) }}">
                    @csrf
                    @include('partials.people-list', ['name' => 'people', 'people' => old('people', []), 'limit' => $view['people_limit']])
                    <button class="mt-4 rounded-xl bg-[#0D47A1] px-6 py-3 font-bold text-white hover:bg-[#1565C0]">Send the list</button>
                </form>
            </section>
        @endif

        {{-- Liquidation --}}
        @if ($canLiquidate || $liquidation)
            <section class="{{ $card }}" id="liquidation">
                <h2 class="mb-1 text-lg font-bold text-gray-900">Liquidation report</h2>
                <p class="mb-4 text-sm text-gray-500">Show how the {{ $peso($disbursement['amount'] ?? $view['granted']) }} released on {{ $day($disbursement['released_at']) }} was used: receipts or a liquidation sheet, and the list of people who received the donation.</p>

                @if (($liquidation['status'] ?? null) === 'returned')
                    <p class="mb-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-900"><strong>Returned for changes:</strong> {{ $liquidation['review_remarks'] ?: 'Please review and resubmit.' }}</p>
                @elseif (($liquidation['status'] ?? null) === 'submitted')
                    <p class="mb-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-900">Submitted {{ $day($liquidation['submitted_at']) }} · under review by the foundation.</p>
                @elseif (($liquidation['status'] ?? null) === 'verified')
                    <p class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-900">Verified {{ $day($liquidation['reviewed_at']) }}. Thank you for reporting!</p>
                @elseif ($view['liquidation_overdue'])
                    <p class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">The liquidation was due on {{ $day($view['liquidation_due']) }}. Please submit it as soon as possible.</p>
                @elseif ($view['liquidation_due'])
                    <p class="mb-4 text-sm text-gray-600">Due by <strong>{{ $day($view['liquidation_due']) }}</strong>.</p>
                @endif

                @if ($canLiquidate)
                    <form method="POST" action="{{ route('fund-request.liquidation', $view['id']) }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        @csrf
                        <div><label class="mb-1 block text-sm font-semibold text-gray-700" for="receipts">Receipts / liquidation sheet <span class="text-red-500">*</span></label><input id="receipts" type="file" name="receipts[]" multiple required accept="image/*,.pdf" class="block w-full text-sm"><p class="mt-1 text-xs text-gray-400">Up to 10 files, JPG/PNG/PDF.</p></div>
                        <div><label class="mb-1 block text-sm font-semibold text-gray-700" for="recipient_list">Signed list of recipients <span class="text-red-500">*</span></label><input id="recipient_list" type="file" name="recipient_list" required accept="image/*,.pdf" class="block w-full text-sm"><p class="mt-1 text-xs text-gray-400">Photo or scan of the list with names and signatures.</p></div>
                        <div><label class="mb-1 block text-sm font-semibold text-gray-700" for="amount_spent">Amount spent (₱) <span class="text-red-500">*</span></label><input id="amount_spent" type="number" step="0.01" min="0" name="amount_spent" required value="{{ old('amount_spent') }}" class="w-full rounded-lg border border-gray-300 px-4 py-2"></div>
                        <div><label class="mb-1 block text-sm font-semibold text-gray-700" for="photos">Distribution photos <span class="font-normal text-gray-400">(optional)</span></label><input id="photos" type="file" name="photos[]" multiple accept="image/*" class="block w-full text-sm"></div>
                        @php($listed = $view['recipients'] ?: array_map(fn ($name) => ['last' => '', 'first' => $name, 'middle' => ''], $view['beneficiaries']))
                        <div class="md:col-span-2"><span class="mb-1 block text-sm font-semibold text-gray-700">People who received the donation <span class="font-normal text-gray-400">(check the list; add or remove people)</span></span>
                            @include('partials.people-list', ['name' => 'recipients', 'people' => old('recipients', $listed), 'limit' => 100, 'required' => false])</div>
                        <div class="md:col-span-2"><label class="mb-1 block text-sm font-semibold text-gray-700" for="notes">Notes <span class="font-normal text-gray-400">(optional)</span></label><textarea id="notes" name="notes" rows="2" maxlength="2000" class="w-full rounded-lg border border-gray-300 px-4 py-2">{{ old('notes') }}</textarea></div>
                        <div class="md:col-span-2"><button class="rounded-xl bg-[#0D47A1] px-6 py-3 font-bold text-white hover:bg-[#1565C0]">Submit liquidation</button></div>
                    </form>
                @elseif ($liquidation)
                    <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                        <div><dt class="text-gray-500">Amount spent</dt><dd class="font-semibold">{{ $peso($liquidation['amount_spent'] ?? 0) }}</dd></div>
                        <div><dt class="text-gray-500">Receipts</dt><dd class="font-semibold">{{ count($liquidation['receipt_files']) }} file(s)</dd></div>
                        <div><dt class="text-gray-500">Recipients listed</dt><dd class="font-semibold">{{ count((array) ($liquidation['recipient_names'] ?? [])) ?: '—' }}</dd></div>
                    </dl>
                @endif
            </section>
        @endif

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <section class="{{ $card }}" id="details">
                <h2 class="mb-4 text-lg font-bold text-gray-900">Request</h2>
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-gray-500">Purpose</dt><dd class="whitespace-pre-line text-gray-900">{{ $view['purpose'] ?: '—' }}</dd></div>
                    <div class="grid grid-cols-3 gap-3">
                        <div><dt class="text-gray-500">People</dt><dd class="font-semibold">{{ $view['beneficiary_count'] ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Per person</dt><dd class="font-semibold">{{ $view['need']['per_person'] ? $peso($view['need']['per_person']) : 'To be decided' }}</dd></div>
                        <div><dt class="text-gray-500">Amount needed</dt><dd class="font-semibold">{{ $view['need']['amount'] !== null ? $peso($view['need']['amount']) : 'After the assessment' }}</dd></div>
                    </div>
                    @if ($view['granted'] !== null)<div><dt class="text-gray-500">Approved amount</dt><dd class="text-lg font-bold text-green-700">{{ $peso($view['granted']) }}</dd></div>@endif
                    @foreach ($view['contact'] as $label => $value)@if ($value)<div><dt class="text-gray-500">{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif @endforeach
                </dl>
            </section>

            <section class="{{ $card }}" @if ($stage['key'] !== 'awaiting_recipients') id="release" @endif>
                <h2 class="mb-4 text-lg font-bold text-gray-900">Release of funds</h2>
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-gray-500">Method</dt><dd class="font-semibold">{{ $disbursement['method_label'] }}</dd></div>
                    @if ($disbursement['provider'])
                        <div><dt class="text-gray-500">{{ $disbursement['mode'] === 'e_wallet' ? 'E-wallet' : 'Bank account' }}</dt><dd>{{ $disbursement['provider'] }} · {{ $disbursement['account_name'] }} · {{ $disbursement['account_number'] }}</dd></div>
                    @endif
                    @if ($disbursement['released_at'])
                        <div><dt class="text-gray-500">Released</dt><dd>{{ $peso($disbursement['amount']) }} on {{ $day($disbursement['released_at']) }}@if ($disbursement['reference_no']) · ref. {{ $disbursement['reference_no'] }}@endif</dd></div>
                    @endif
                </dl>
            </section>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <section class="{{ $card }}" id="documents">
                <h2 class="mb-1 text-lg font-bold text-gray-900">Documents</h2>
                <p class="mb-4 text-xs text-gray-500">The foundation reviews each document. If one needs a clearer or updated copy, upload it here.</p>
                <ul class="space-y-2 text-sm">
                    @foreach ($view['documents'] as $document)
                        @php($docStatus = $document['status'] ?? null)
                        <li class="rounded-lg bg-gray-50 px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ $document['label'] }}
                                    @if ($docStatus === 'verified')<span class="ml-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Verified</span>
                                    @elseif ($docStatus === 'resubmit')<span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Please resubmit</span>
                                    @elseif ($docStatus === 'missing')<span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Missing</span>
                                    @elseif ($docStatus === 'pending')<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Under review</span>@endif
                                </span>
                                @if ($document['url'])<a class="font-semibold text-blue-600 hover:underline" href="{{ $document['url'] }}" target="_blank" rel="noopener">View</a>@endif
                            </div>
                            @if ($docStatus === 'resubmit' && $document['remarks'])<p class="mt-1 text-xs text-red-700">Foundation: “{{ $document['remarks'] }}”</p>@endif
                            @if (in_array($docStatus, ['resubmit', 'missing'], true) && $view['status'] === 'pending' && ! empty($document['field']))
                                <form method="POST" action="{{ route('fund-request.document', [$view['id'], $document['field']]) }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="file" name="file" required accept="image/*,.pdf" class="text-xs">
                                    <button class="rounded-lg bg-[#0D47A1] px-3 py-1.5 text-xs font-semibold text-white">Upload new {{ strtolower($document['label']) }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
            <section class="{{ $card }}" id="history">
                <h2 class="mb-4 text-lg font-bold text-gray-900">History</h2>
                <ol class="space-y-3 border-l-2 border-gray-200 pl-4 text-sm">
                    @foreach ($view['timeline'] as $event)
                        <li><strong class="block text-gray-900">{{ $event['label'] }}</strong><span class="text-gray-500">{{ \Carbon\Carbon::parse($event['at'])->format('M d, Y g:i A') }}@if ($event['detail']) · {{ $event['detail'] }}@endif</span></li>
                    @endforeach
                </ol>
            </section>
        </div>

        @if ($view['beneficiaries'])
            <details class="{{ $card }}"><summary class="cursor-pointer text-lg font-bold text-gray-900">People listed ({{ count($view['beneficiaries']) }})</summary>
                <ol class="mt-4 grid list-decimal grid-cols-1 gap-x-8 pl-6 text-sm text-gray-700 md:grid-cols-2">@foreach ($view['beneficiaries'] as $name)<li>{{ $name }}</li>@endforeach</ol>
            </details>
        @endif

        {{-- Conversation with the foundation --}}
        <section class="{{ $card }}" id="messages" data-thread="{{ route('fund-request.messages', $view['id']) }}">
            <h2 class="mb-1 text-lg font-bold text-gray-900">Messages with the foundation</h2>
            <p class="mb-4 text-sm text-gray-500">Ask about your interview, the assessment, or your documents. You'll get a notification when the foundation replies.</p>
            <div class="max-h-96 overflow-y-auto rounded-lg border border-gray-100 bg-white p-3" data-thread-list>@include('FundPage.partials.messages', ['thread' => $thread, 'side' => 'requester'])</div>
            <form method="POST" action="{{ route('fund-request.messages.store', $view['id']) }}" class="mt-3 flex gap-2" data-thread-form>
                @csrf
                <textarea name="body" required maxlength="2000" rows="2" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1976D2]" placeholder="Write a message… (Ctrl+Enter to send)"></textarea>
                <button class="self-end rounded-lg bg-[#0D47A1] px-5 py-2 font-semibold text-white hover:bg-[#1565C0]" type="submit">Send</button>
            </form>
        </section>
    </div>
</div>
@endsection
