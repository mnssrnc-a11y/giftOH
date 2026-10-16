@extends('layouts.dashboard')

@section('title', 'Fund Request - Gift of Hope')

@section('content')
@php
    $limits = config('funding.beneficiaries');
    $assessmentDays = config('funding.assessment_days');
    $modes = config('funding.transfer_modes');
    $organizations = config('funding.known_organizations');
    $steps = [1 => 'Organization', 2 => 'Address', 3 => 'Your need', 4 => 'Receiving funds', 5 => 'Documents', 6 => 'Review & submit'];
    // Which step each field is on, so every error can point to its step.
    $fieldStep = ['org_choice' => 1, 'org_name_other' => 1, 'contact_person' => 1, 'contact_email' => 1, 'phone' => 1,
        'street' => 2, 'barangay' => 2, 'municipality' => 2,
        'categories' => 3, 'purpose' => 3, 'beneficiary_count' => 3,
        'transfer_mode' => 4, 'provider' => 4, 'account_name' => 4, 'account_number' => 4] + array_fill_keys(array_keys(config('funding.requirements')), 5);
    $errorList = collect($errors->getMessages())->map(fn ($messages, $key) => ['field' => explode('.', $key)[0], 'step' => $fieldStep[explode('.', $key)[0]] ?? 6, 'message' => $messages[0]])
        ->unique('field')->sortBy('step')->values();
    $oldChoice = old('org_choice');
    $formData = [
        'limits' => $limits,
        'modes' => collect($modes)->map(fn ($mode) => ['label' => $mode['label'], 'providers' => $mode['providers'], 'account' => $mode['account']])->all(),
        'oldProvider' => old('provider'),
        'steps' => $steps,
        'fieldStep' => $fieldStep,
        'startStep' => $errorList->min('step') ?? 1,
    ];
@endphp
<script>window.giftOfHopeFundForm = @json($formData);</script>

<div class="hope-page fr">
    <div class="hope-heading">
        <div>
            <div class="hope-eyebrow">FUND REQUEST</div>
            <h1>Ask for help for your community.</h1>
            <p>Six short steps. Any organization may ask for help for {{ $limits['min'] }}–{{ $limits['max'] }} people.</p>
        </div>
    </div>

    @if ($denialReason)
        <div class="hope-preview hope-preview-error" role="alert"><strong>You can't submit a new request right now.</strong><br>{{ $denialReason }}</div>
    @endif

    {{-- Everything that is missing or wrong, in one list, each linked to its step. --}}
    <div class="fr-errors" data-fr-errors role="alert" tabindex="-1" @if ($errorList->isEmpty()) hidden @endif>
        <strong data-fr-errors-title>Please complete {{ $errorList->count() }} {{ \Illuminate\Support\Str::plural('item', $errorList->count()) }}:</strong>
        <ul data-fr-errors-list>
            @foreach ($errorList as $error)
                <li><a href="#step-{{ $error['step'] }}" data-goto-step="{{ $error['step'] }}" data-focus="{{ $error['field'] }}">Step {{ $error['step'] }} · {{ $steps[$error['step']] }}</a>: {{ $error['message'] }}</li>
            @endforeach
        </ul>
        @if ($errorList->isNotEmpty())
            <p class="fr-note">For your security, attached documents are not kept after a failed submit. Attach them again in step 5.</p>
        @endif
    </div>

    <form method="POST" action="{{ route('fund-request.store') }}" enctype="multipart/form-data" class="fr-form" data-fund-form novalidate>
        @csrf
        <ol class="fr-stepper" aria-label="Steps">
            @foreach ($steps as $n => $name)
                <li><a href="#step-{{ $n }}" class="fr-step" data-goto-step="{{ $n }}" data-step-link="{{ $n }}"><span class="fr-step-num">{{ $n }}</span><span class="fr-step-name">{{ $name }}</span></a></li>
            @endforeach
        </ol>
        <p class="fr-legend">Fields marked <span class="fr-req">*</span> are required.</p>

        <fieldset class="fr-fieldset" @disabled($denialReason)>
            <section class="fr-panel" id="step-1" data-step="1" aria-labelledby="step-1-title">
                <h2 id="step-1-title">1. Organization</h2>
                <p>Who is asking for help, and how the foundation can reach you.</p>
                <div class="fr-grid">
                    <x-fr-field name="org_choice" label="Organization" wide>
                        <select id="org_choice" name="org_choice" required aria-describedby="org_choice-error" data-org-choice>
                            <option value="">Choose your organization…</option>
                            <optgroup label="Associated with SACOP">
                                @foreach (\App\Support\SacopAssociated::names() as $organization)<option value="{{ $organization }}" @selected($oldChoice === $organization)>{{ $organization }}</option>@endforeach
                            </optgroup>
                            <optgroup label="Charity homes">
                                @foreach ($organizations as $organization)<option value="{{ $organization }}" @selected($oldChoice === $organization)>{{ $organization }}</option>@endforeach
                            </optgroup>
                            <option value="other" @selected($oldChoice === 'other')>Others (not on the list)</option>
                        </select>
                    </x-fr-field>
                    <x-fr-field name="org_name_other" label="Name of your organization" wide maxlength="255" autocomplete="organization" placeholder="e.g. Bahay Pag-asa Foundation Inc." data-org-other data-receiver-name />
                    <x-fr-field name="contact_person" label="Contact person" maxlength="255" autocomplete="name" placeholder="e.g. Maria Santos" data-receiver-name />
                    <x-fr-field name="contact_email" label="Contact email" type="email" placeholder="e.g. hopehome@gmail.com" :value="auth()->user()->email" maxlength="255" autocomplete="email" />
                    <x-fr-field name="phone" label="Contact number" type="tel" :value="auth()->user()->phone" inputmode="tel" maxlength="13" autocomplete="tel" placeholder="09171234567" pattern="(\+?63|0)9[0-9]{9}" />
                </div>
            </section>

            <section class="fr-panel" id="step-2" data-step="2" aria-labelledby="step-2-title">
                <h2 id="step-2-title">2. Address</h2>
                <p>Where the organization is in Pampanga. Choose the city or town, then the barangay.</p>
                <div class="fr-grid">
                    <div class="fr-field" data-field="province">
                        <label for="province">Province</label>
                        <input id="province" value="{{ \App\Support\Pampanga::PROVINCE }}" readonly aria-describedby="province-hint" data-address-part="4">
                        <small id="province-hint">Help is given in Pampanga only.</small>
                    </div>
                    <x-fr-field name="municipality" label="City / municipality">
                        <select id="municipality" name="municipality" required aria-describedby="municipality-error" data-place-town data-address-part="3">
                            <option value="">Choose the city or town…</option>
                            @foreach (\App\Support\Pampanga::towns() as $town)<option @selected(old('municipality') === $town)>{{ $town }}</option>@endforeach
                        </select>
                    </x-fr-field>
                    <x-fr-field name="barangay" label="Barangay">
                        @include('partials.barangay-select', ['name' => 'barangay', 'town' => old('municipality'), 'extra' => 'aria-describedby="barangay-error" data-address-part="2"'])
                    </x-fr-field>
                    <x-fr-field name="street" label="Street / house no." maxlength="150" autocomplete="address-line1" placeholder="e.g. 123 Rizal St." data-address-part="1" />
                </div>
                <p class="fr-preview" data-address-preview aria-live="polite"></p>
            </section>

            <section class="fr-panel" id="step-3" data-step="3" aria-labelledby="step-3-title">
                <h2 id="step-3-title">3. Your need</h2>
                <p>Choose every kind of help you need. You can pick more than one.</p>
                @php($chosen = (array) old('categories', []))
                <x-fr-field name="categories" label="Kinds of help" wide>
                    <div class="fr-choices" role="group" aria-labelledby="categories-label" aria-describedby="categories-error" data-categories>
                        @foreach ($categories as $category)
                            <label class="fr-choice"><input type="checkbox" name="categories[]" value="{{ $category['key'] }}" @checked(in_array($category['key'], $chosen, true))><span>{{ $category['label'] }}</span></label>
                        @endforeach
                    </div>
                </x-fr-field>
                <div class="fr-grid">
                    <x-fr-field name="purpose" label="Purpose of the request" wide hint="As stated in your formal letter: what the help is for and why it is needed.">
                        <textarea id="purpose" name="purpose" placeholder="e.g. Rice, canned goods and hygiene kits for 30 elderly residents for one month." required rows="5" maxlength="5000" aria-describedby="purpose-error purpose-hint">{{ old('purpose') }}</textarea>
                    </x-fr-field>
                    <x-fr-field name="beneficiary_count" label="How many people will receive the help?" type="number" inputmode="numeric" min="{{ $limits['min'] }}" max="{{ $limits['max'] }}" step="1" placeholder="e.g. {{ intdiv($limits['min'] + $limits['max'], 2) }}"
                        hint="Between {{ $limits['min'] }} and {{ $limits['max'] }}. You list their names after approval, when the funds are released." />
                </div>
            </section>

            <section class="fr-panel" id="step-4" data-step="4" aria-labelledby="step-4-title">
                <h2 id="step-4-title">4. Receiving the funds</h2>
                <p>The funds go to a bank account or e-wallet in the receiver's name: the organization or the contact person.</p>
                <div class="fr-grid">
                    <x-fr-field name="transfer_mode" label="Receive through">
                        <select id="transfer_mode" name="transfer_mode" required aria-describedby="transfer_mode-error" data-transfer-mode>
                            <option value="">Choose bank or e-wallet…</option>
                            @foreach ($modes as $key => $mode)<option value="{{ $key }}" @selected(old('transfer_mode') === $key)>{{ $mode['label'] }}</option>@endforeach
                        </select>
                    </x-fr-field>
                    <x-fr-field name="provider" label="Bank or e-wallet">
                        <select id="provider" name="provider" required aria-describedby="provider-error" data-provider>
                            <option value="">Choose “Receive through” first</option>
                            @foreach ($modes as $key => $mode)
                                <optgroup label="{{ $mode['label'] }}">@foreach ($mode['providers'] as $provider)<option value="{{ $provider }}" @selected(old('provider') === $provider)>{{ $provider }}</option>@endforeach</optgroup>
                            @endforeach
                        </select>
                    </x-fr-field>
                    <x-fr-field name="account_name" label="Account name" placeholder="e.g. Hope Home Foundation Inc." maxlength="255" hint="Exactly as on the account. It must match the organization name or the contact person." data-account-name />
                    <x-fr-field name="account_number" label="Account number" placeholder="e.g. 09171234567" maxlength="255" autocomplete="off" data-account-number />
                </div>
                <p class="fr-preview" data-account-hint aria-live="polite"></p>
            </section>

            <section class="fr-panel" id="step-5" data-step="5" aria-labelledby="step-5-title">
                <h2 id="step-5-title">5. Documents</h2>
                <p>JPG, PNG or PDF, up to 10 MB each. Every file is scanned for malware.</p>
                <div class="fr-grid">
                    @foreach (config('funding.requirements') as $field => $document)
                        <x-fr-field :name="$field" :label="$document['label']" :hint="$document['hint']">
                            <label class="fr-file" for="{{ $field }}"><span data-file-name>Choose a file</span></label>
                            <input type="file" id="{{ $field }}" name="{{ $field }}" accept=".jpg,.jpeg,.png,.pdf" required class="sr-only" aria-describedby="{{ $field }}-error {{ $field }}-hint" data-file-input data-max-mb="10">
                        </x-fr-field>
                    @endforeach
                </div>
            </section>

            <section class="fr-panel" id="step-6" data-step="6" aria-labelledby="step-6-title">
                <h2 id="step-6-title">6. Review & submit</h2>
                <p>Check your answers. Select a step above to change something.</p>
                <dl class="fr-review" data-fr-review><div><dt>Your answers</dt><dd>Turn on JavaScript to see a summary, or check each step above.</dd></div></dl>
                <p class="fr-note">After you submit, your request is <strong>Under assessment</strong>: a social worker contacts you for an interview within {{ $assessmentDays }} days.</p>
                <button type="submit" class="hope-button fr-submit">Submit request</button>
            </section>

            <div class="fr-nav" data-fr-nav>
                <button type="button" class="hope-button secondary" data-fr-prev>← Back</button>
                <span class="fr-nav-count" data-fr-count></span>
                <button type="button" class="hope-button" data-fr-next>Next →</button>
            </div>
        </fieldset>
    </form>
</div>
@endsection
