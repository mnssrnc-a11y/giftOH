{{--
    The social worker's assessment, with the amount the request needs: the admin / social worker
    decides it from the interview (what the people need, how many qualify). Recording it again
    while the request is pending replaces it.
--}}
@php($recordedAmount = $assessment['amount_needed'] ?? null)
<form method="POST" action="{{ route('admin.fund-request.assessment', $view['id']) }}" class="admin-post-form">
    @csrf
    <div class="admin-field"><label for="outcome">Outcome</label><select id="outcome" name="outcome" class="admin-select" style="width:100%"><option value="recommended" @selected(old('outcome', $assessment['outcome'] ?? '') === 'recommended')>Recommended for assistance</option><option value="not_recommended" @selected(old('outcome', $assessment['outcome'] ?? '') === 'not_recommended')>Not recommended</option></select></div>
    <div class="admin-field"><label for="verified_beneficiaries">People verified as qualified</label><input id="verified_beneficiaries" type="number" placeholder="e.g. 25" name="verified_beneficiaries" min="0" max="{{ config('funding.beneficiaries.max') }}" required value="{{ old('verified_beneficiaries', $assessment['verified_beneficiaries'] ?? $view['beneficiary_count']) }}"></div>
    <div class="admin-field">
        <label for="amount_needed">Amount needed (₱) <span style="font-weight:400">(when recommended)</span></label>
        <input id="amount_needed" type="number" placeholder="e.g. 12500" name="amount_needed" min="1" step="0.01" data-amount-buttons value="{{ old('amount_needed', $recordedAmount) }}" aria-describedby="amount_needed-hint">
        <p class="admin-field-hint" id="amount_needed-hint">How much the request needs, based on the assessment.@if ($guideline !== null) Guideline: about {{ $peso($cap) }} per person ({{ $peso($guideline) }} for {{ $people }} people).@endif</p>
    </div>
    <div class="admin-field"><label for="amount_basis">How the amount was decided <span style="font-weight:400">(optional)</span></label><input id="amount_basis" name="amount_basis" maxlength="1000" placeholder="e.g. ₱500 per person × 30 people for rice and canned goods" value="{{ old('amount_basis', $assessment['amount_basis'] ?? '') }}"></div>
    <div class="admin-field"><label for="assessment_sw">Assessed by (social worker)</label><input id="assessment_sw" name="social_worker" placeholder="e.g. Liza Ramos, RSW" maxlength="120" value="{{ old('social_worker', ($assessment['social_worker'] ?? '') ?: auth()->user()->fullName()) }}"></div>
    <div class="admin-field"><label for="assessment_notes">Findings</label><textarea id="assessment_notes" name="notes" placeholder="e.g. Home visit done. 25 of 30 residents qualify; they need rice and medicines for one month." class="admin-textarea" style="min-height:90px" required maxlength="5000">{{ old('notes', $assessment['notes'] ?? '') }}</textarea></div>
    <div><button class="admin-button success" type="submit">Save assessment</button></div>
</form>
