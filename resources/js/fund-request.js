// Fund request form: beneficiary count and receiver account check. Every category needs the same
// documents and a bank account in the receiver's name.
// The per-person budget is decided by an admin after the assessment, not by the requester.
const form = document.querySelector('[data-fund-form]');
const config = window.giftOfHopeFundForm;

if (form && config) {
    const $ = selector => form.querySelector(selector);
    const $$ = selector => [...form.querySelectorAll(selector)];
    const normalize = value => String(value ?? '').toLowerCase().replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
    const currentCategory = () => $('[data-category-input]:checked')?.value ?? null;

    function beneficiaryNames() {
        const names = $('[data-beneficiaries]').value.split(/\r?\n/).map(line => line.replace(/^\s*\d+[.)]\s*/, '').trim()).filter(Boolean);
        return [...new Set(names)];
    }

    function renderBeneficiaries() {
        const count = beneficiaryNames().length;
        const { min, max } = config.limits;
        const ok = count >= min && count <= max;
        const note = $('[data-beneficiary-count]');
        note.textContent = `${count} ${count === 1 ? 'person' : 'people'} listed${ok ? '' : ` · must be between ${min} and ${max}`}`;
        note.className = `mt-2 text-sm ${ok ? 'text-green-700' : 'text-amber-700'}`;
    }

    function checkAccountName() {
        const hint = $('[data-account-hint]');
        const account = normalize($('[data-account-name]')?.value);
        if (!hint || !account) { if (hint) hint.textContent = ''; return; }
        const matches = $$('[data-receiver-name]').some(input => normalize(input.value) === account);
        hint.textContent = matches ? '✓ Account name matches the receiver.' : 'The account name must match the organization name or the contact person exactly.';
        hint.className = `mt-2 text-xs ${matches ? 'text-green-700' : 'text-amber-700'}`;
    }

    form.addEventListener('change', event => {
        if (event.target.matches('[data-file-input]')) {
            const label = event.target.closest('div').querySelector('[data-file-name]');
            const name = event.target.files?.[0]?.name;
            label.textContent = name ? (name.length > 26 ? `${name.slice(0, 24)}…` : name) : 'Click to upload';
            label.classList.toggle('text-[#1E3A8A]', Boolean(name));
            label.classList.toggle('font-semibold', Boolean(name));
        }
    });

    form.addEventListener('input', event => {
        if (event.target.matches('[data-beneficiaries]')) renderBeneficiaries();
        if (event.target.matches('[data-account-name],[data-receiver-name]')) checkAccountName();
    });

    form.addEventListener('submit', event => {
        const { min, max } = config.limits;
        const count = beneficiaryNames().length;
        const problem = !currentCategory() ? 'Choose a category.'
            : count < min || count > max ? `List between ${min} and ${max} people (currently ${count}).` : null;
        if (problem) {
            event.preventDefault();
            alert(problem);
            return;
        }
        form.querySelector('[type=submit]').disabled = true;
    });

    renderBeneficiaries();
    checkAccountName();
}
