@extends('layouts.dashboard')

@section('title', 'Fund Request - Gift of Hope')

@section('content')
@php
    $limits = config('funding.beneficiaries');
    $assessmentDays = config('funding.assessment_days');
    $liquidationDays = config('funding.liquidation_days');
    $formData = [
        'categories' => $categories,
        'limits' => $limits,
    ];
    $input = 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1976D2] bg-white';
    $label = 'block text-sm font-semibold text-gray-700 mb-2';
@endphp
<script>window.giftOfHopeFundForm = @json($formData);</script>

<div class="hope-page">
    <div class="hope-heading">
        <div>
            <div class="hope-eyebrow">FUND REQUEST</div>
            <h1>Ask for help for your community.</h1>
            <p>Any organization may request assistance for {{ $limits['min'] }}–{{ $limits['max'] }} people.</p>
        </div>
    </div>

    <div>
        <div class="max-w-4xl space-y-6">
            <ol class="grid grid-cols-2 gap-3 rounded-xl border border-gray-100 bg-white p-5 text-xs text-gray-600 shadow-sm md:grid-cols-5">
                @foreach ([['1', 'Submit request', 'With the required documents'], ['2', 'Interview & assessment', "By a social worker within {$assessmentDays} days"], ['3', 'Approval', 'Admin review, then final approval'], ['4', 'Release of funds', 'Bank transfer to your account'], ['5', 'Liquidation', "Receipts + list of recipients within {$liquidationDays} days"]] as [$n, $title, $text])
                    <li class="flex gap-2"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#0D47A1] text-white font-bold">{{ $n }}</span><span><strong class="block text-gray-900">{{ $title }}</strong>{{ $text }}</span></li>
                @endforeach
            </ol>

            @if ($denialReason)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800" role="alert">
                    <strong class="block">You can't submit a new request right now.</strong>{{ $denialReason }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
                    <p class="mb-2 text-sm font-semibold text-red-800">Please fix the following:</p>
                    <ul class="list-disc space-y-1 pl-5 text-sm text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('fund-request.store') }}" enctype="multipart/form-data" data-fund-form novalidate>
                @csrf
                <fieldset class="space-y-6" @disabled($denialReason)>

                {{-- Organization --}}
                <section class="rounded-xl border border-gray-100 bg-white p-8 shadow-sm">
                    <h2 class="mb-1 text-xl font-bold text-gray-900">1. Organization & contact</h2>
                    <p class="mb-6 text-sm text-gray-500">All organizations are welcome: charity homes, schools, parishes, community groups, and others.</p>
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="{{ $label }}" for="org_name">Organization name</label>
                            <input id="org_name" name="org_name" list="known-orgs" required maxlength="255" value="{{ old('org_name') }}" class="{{ $input }}" placeholder="Type your organization's name" data-receiver-name>
                            <datalist id="known-orgs">@foreach (config('funding.known_organizations') as $organization)<option value="{{ $organization }}">@endforeach</datalist>
                        </div>
                        <div><label class="{{ $label }}" for="contact_person">Contact person</label><input id="contact_person" name="contact_person" required maxlength="255" value="{{ old('contact_person') }}" class="{{ $input }}" data-receiver-name></div>
                        <div><label class="{{ $label }}" for="contact_email">Contact email</label><input id="contact_email" name="contact_email" type="email" required value="{{ old('contact_email', auth()->user()->email) }}" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="phone">Contact number</label><input id="phone" name="phone" required maxlength="30" value="{{ old('phone', auth()->user()->phone) }}" class="{{ $input }}" placeholder="09123456789"></div>
                        <div><label class="{{ $label }}" for="tax_id">Tax ID / registration no. <span class="font-normal text-gray-400">(optional)</span></label><input id="tax_id" name="tax_id" maxlength="255" value="{{ old('tax_id') }}" class="{{ $input }}"></div>
                        <div class="md:col-span-2"><label class="{{ $label }}" for="address">Address</label><input id="address" name="address" required maxlength="255" value="{{ old('address') }}" class="{{ $input }}" placeholder="Street, barangay, city, province"></div>
                    </div>
                </section>

                {{-- Category & purpose --}}
                <section class="rounded-xl border border-gray-100 bg-white p-8 shadow-sm">
                    <h2 class="mb-1 text-xl font-bold text-gray-900">2. What is the request for?</h2>
                    <p class="mb-6 text-sm text-gray-500">Choose what the help is for. Every request needs a formal letter and a Certificate of Indigency.</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2" role="radiogroup" aria-label="Category">
                        @foreach ($categories as $category)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4 hover:border-[#1976D2] has-[:checked]:border-[#1976D2] has-[:checked]:bg-blue-50">
                                <input type="radio" name="category" value="{{ $category['key'] }}" required class="mt-1" @checked(old('category') === $category['key']) data-category-input>
                                <span><strong class="block text-sm text-gray-900">{{ $category['label'] }}</strong></span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-6">
                        <label class="{{ $label }}" for="purpose">Purpose of the request</label>
                        <textarea id="purpose" name="purpose" required rows="5" maxlength="5000" class="{{ $input }}" placeholder="As stated in your formal letter: what the money will buy, for whom, and why it is needed.">{{ old('purpose') }}</textarea>
                        <p class="mt-2 text-xs text-gray-500">The foundation decides the budget per person after the social worker's assessment, based on your letter and its price reference.</p>
                    </div>
                </section>

                {{-- Beneficiaries --}}
                <section class="rounded-xl border border-gray-100 bg-white p-8 shadow-sm">
                    <h2 class="mb-1 text-xl font-bold text-gray-900">3. People who will receive the help</h2>
                    <p class="mb-4 text-sm text-gray-500">List each person who will receive the assistance. One full name per line, between {{ $limits['min'] }} and {{ $limits['max'] }} people.</p>
                    <textarea id="beneficiaries" name="beneficiaries" required rows="10" class="{{ $input }} font-mono text-sm" placeholder="Juan Dela Cruz&#10;Maria Santos&#10;…" data-beneficiaries>{{ old('beneficiaries') }}</textarea>
                    <p class="mt-2 text-sm" data-beneficiary-count aria-live="polite"></p>
                </section>

                {{-- Bank --}}
                <section class="rounded-xl border border-gray-100 bg-white p-8 shadow-sm">
                    <h2 class="mb-1 text-xl font-bold text-gray-900">4. Where to send the funds</h2>
                    <p class="mb-6 text-sm text-gray-500">Funds are sent by bank-to-bank transfer to any bank, as long as the account is in the receiver's name: the organization or the contact person above.</p>
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                        <div><label class="{{ $label }}" for="bank_name">Bank</label><input id="bank_name" name="bank_name" maxlength="120" value="{{ old('bank_name') }}" class="{{ $input }}" placeholder="e.g. BDO, Landbank, GCash" required></div>
                        <div><label class="{{ $label }}" for="account_name">Account name</label><input id="account_name" name="account_name" maxlength="255" value="{{ old('account_name') }}" class="{{ $input }}" required data-account-name></div>
                        <div><label class="{{ $label }}" for="account_number">Account number</label><input id="account_number" name="account_number" inputmode="numeric" maxlength="30" value="{{ old('account_number') }}" class="{{ $input }}" required></div>
                    </div>
                    <p class="mt-2 text-xs" data-account-hint aria-live="polite"></p>
                </section>

                {{-- Requirements --}}
                <section class="rounded-xl border border-gray-100 bg-white p-8 shadow-sm">
                    <h2 class="mb-1 text-xl font-bold text-gray-900">5. Requirements</h2>
                    <p class="mb-6 text-sm text-gray-500">JPG, PNG or PDF, up to 10 MB each. Every file is scanned for malware before it is accepted.</p>
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            @foreach (config('funding.requirements') as $field => $document)
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-gray-700" for="{{ $field }}">{{ $document['label'] }} <span class="text-red-500">*</span></label>
                                    <p class="mb-2 text-xs text-gray-400">{{ $document['hint'] }}</p>
                                    <label for="{{ $field }}" class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-4 py-5 transition-colors hover:border-[#1976D2] hover:bg-blue-50">
                                        <span class="text-sm text-gray-400" data-file-name>Click to upload</span>
                                    </label>
                                    <input type="file" id="{{ $field }}" name="{{ $field }}" accept=".jpg,.jpeg,.png,.pdf" required class="sr-only" data-file-input>
                                    @error($field)<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                </section>

                <button type="submit" class="w-full rounded-xl bg-[#0D47A1] py-3.5 font-bold text-white transition-colors hover:bg-[#1565C0] disabled:opacity-50">Submit request</button>
                <p class="text-center text-xs text-gray-500">After you submit, a social worker will contact you to schedule an interview and assessment within {{ $assessmentDays }} days.</p>
                </fieldset>
            </form>
        </div>
    </div>
</div>
@endsection
