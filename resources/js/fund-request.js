// Fund request form: six steps you can open in any order (the step bar and Next/Back), with the
// browser's Back button returning to the previous step. "Others" reveals the organization name,
// the bank/e-wallet list follows "Receive through", and on submit every missing or wrong field is
// listed at the top with a link to its step, besides its own message under the field.
const form = document.querySelector('[data-fund-form]');
const config = window.giftOfHopeFundForm;

if (form && config) {
    const $ = (selector, scope = form) => scope.querySelector(selector);
    const $$ = (selector, scope = form) => [...scope.querySelectorAll(selector)];
    const page = form.closest('.fr');
    const panels = $$('[data-step]');
    const last = panels.length;
    const summary = page.querySelector('[data-fr-errors]');
    const normalize = value => String(value ?? '').toLowerCase().replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let current = 1;

    form.classList.add('is-wizard');

    /* ---------- Steps ---------- */

    function show(step, { push = true, focus = true, scroll = true } = {}) {
        current = Math.min(Math.max(Number(step) || 1, 1), last);
        panels.forEach(panel => { panel.hidden = Number(panel.dataset.step) !== current; });
        $$('[data-step-link]').forEach(link => {
            const n = Number(link.dataset.stepLink);
            link.classList.toggle('is-current', n === current);
            link.toggleAttribute('aria-current', n === current);
            if (n === current) link.setAttribute('aria-current', 'step');
        });
        // Keep the open step visible in the step bar (it scrolls sideways on phones).
        const stepper = $('.fr-stepper');
        const link = $(`[data-step-link="${current}"]`).parentElement;
        stepper.scrollTo({ left: link.offsetLeft - (stepper.clientWidth - link.clientWidth) / 2, behavior: 'smooth' });
        $('[data-fr-prev]').hidden = current === 1;
        $('[data-fr-next]').hidden = current === last;
        $('[data-fr-count]').textContent = `Step ${current} of ${last}`;
        if (current === last) renderReview();
        if (push && location.hash !== `#step-${current}`) history.pushState({ frStep: current }, '', `#step-${current}`);
        if (focus) $(`#step-${current}-title`)?.focus({ preventScroll: true });
        if (scroll) form.scrollIntoView({ block: 'start', behavior: 'smooth' });
        markSteps();
    }

    // Back / forward between steps.
    window.addEventListener('popstate', () => {
        const match = location.hash.match(/^#step-(\d)$/);
        show(match ? match[1] : 1, { push: false });
    });

    /* ---------- Conditional fields ---------- */

    function syncOrganization() {
        const other = $('[data-org-choice]').value === 'other';
        const field = $('[data-field="org_name_other"]');
        field.hidden = !other;
        $('[data-org-other]').disabled = !other;
    }

    function syncProviders() {
        const mode = $('[data-transfer-mode]').value;
        const select = $('[data-provider]');
        const chosen = select.value || config.oldProvider;
        const providers = config.modes[mode]?.providers ?? [];
        select.innerHTML = `<option value="">${providers.length ? 'Choose…' : 'Choose “Receive through” first'}</option>`
            + providers.map(name => `<option value="${escapeHtml(name)}" ${name === chosen ? 'selected' : ''}>${escapeHtml(name)}</option>`).join('');
        select.disabled = !providers.length;
        syncAccountField();
    }

    // The account field asks for a bank account number, an e-wallet mobile number or a PayPal email.
    function accountKind() {
        const mode = $('[data-transfer-mode]').value;
        const kind = config.modes[mode]?.account;
        return typeof kind === 'object' && kind !== null ? (kind[$('[data-provider]').value] ?? 'number') : (kind ?? 'number');
    }

    function syncAccountField() {
        const input = $('[data-account-number]');
        const label = $('[data-field="account_number"] label');
        const kind = accountKind();
        const text = { number: 'Account number', mobile: 'E-wallet mobile number', email: 'PayPal email' }[kind];
        label.firstChild.textContent = text;
        input.type = kind === 'email' ? 'email' : 'text';
        input.inputMode = kind === 'email' ? 'email' : kind === 'mobile' ? 'tel' : 'numeric';
        input.placeholder = kind === 'email' ? 'name@example.com' : kind === 'mobile' ? '09171234567' : 'Digits only';
    }

    function addressPreview() {
        const parts = $$('[data-address-part]').map(input => input.value.trim()).filter(Boolean);
        $('[data-address-preview]').textContent = parts.length ? `Saved as: ${parts.join(', ')}` : '';
    }

    function receiverNames() {
        const org = $('[data-org-choice]').value === 'other' ? $('[data-org-other]').value : $('[data-org-choice]').value;
        return [org, $('#contact_person').value].map(normalize).filter(Boolean);
    }

    function accountHint() {
        const hint = $('[data-account-hint]');
        const account = normalize($('[data-account-name]').value);
        if (!account) { hint.textContent = ''; return; }
        const ok = receiverNames().includes(account);
        hint.textContent = ok ? '✓ The account name matches the receiver.' : 'The account name must match the organization name or the contact person exactly.';
        hint.classList.toggle('is-ok', ok);
    }

    /* ---------- Checks ---------- */

    const labelOf = name => ($(`[data-field="${name}"] label`)?.firstChild?.textContent ?? name).trim();

    // The message for a field, or '' when it is fine. Hidden (not applicable) fields are skipped.
    function problem(name) {
        const field = $(`[data-field="${name}"]`);
        if (!field || field.hidden) return '';
        const { min, max } = config.limits;
        if (name === 'categories') return $$('[name="categories[]"]:checked').length ? '' : 'Choose at least one kind of help you need.';
        const input = $(`[name="${name}"]`);
        if (!input || input.disabled) return '';
        const value = input.type === 'file' ? input.files?.[0] : input.value.trim();
        if (!value) {
            if (!input.required) return '';
            return {
                org_choice: 'Choose your organization, or “Others” to type its name.',
                provider: $('[data-transfer-mode]').value ? 'Choose the bank or e-wallet.' : 'Choose “Receive through” first, then the bank or e-wallet.',
            }[name] ?? (input.type === 'file' ? `Attach the ${labelOf(name).toLowerCase()}.` : `Enter the ${labelOf(name).toLowerCase()}.`);
        }
        if (name === 'contact_email' && !input.checkValidity()) return 'Enter a valid email address.';
        if (name === 'phone' && !/^(\+?63|0)9\d{9}$/.test(value.replace(/[\s-]/g, ''))) return 'Enter a mobile number like 09171234567.';
        if (name === 'beneficiary_count' && (!Number.isInteger(Number(value)) || value < min || value > max)) return `Enter a number from ${min} to ${max}.`;
        if (name === 'account_name' && !receiverNames().includes(normalize(value))) return 'The account name must match the organization name or the contact person exactly.';
        if (name === 'account_number') {
            const kind = accountKind();
            if (kind === 'email' && !input.checkValidity()) return 'Enter the email address of the PayPal account.';
            if (kind === 'mobile' && !/^(\+?63|0)9\d{9}$/.test(value.replace(/[\s-]/g, ''))) return 'Enter the e-wallet mobile number, like 09171234567.';
            if (kind === 'number' && !/^[0-9 -]{6,30}$/.test(value)) return 'Enter the account number (6 to 30 digits).';
        }
        if (input.type === 'file') {
            const ext = value.name.split('.').pop().toLowerCase();
            if (!['jpg', 'jpeg', 'png', 'pdf'].includes(ext)) return 'Choose a JPG, PNG or PDF file.';
            if (value.size > Number(input.dataset.maxMb) * 1024 * 1024) return `The file must be ${input.dataset.maxMb} MB or smaller.`;
        }
        return '';
    }

    const fieldsOf = step => $$(`[data-step="${step}"] [data-field]`).map(field => field.dataset.field);

    function setError(name, message) {
        const field = $(`[data-field="${name}"]`);
        if (!field) return;
        field.classList.toggle('has-error', Boolean(message));
        $(`[data-error-for="${name}"]`, field).textContent = message;
        $$('input, select, textarea', field).forEach(input => input.toggleAttribute('aria-invalid', Boolean(message)));
    }

    function check(step) {
        const problems = fieldsOf(step).map(name => ({ name, step, message: problem(name) })).filter(item => item.message);
        fieldsOf(step).forEach(name => setError(name, problems.find(item => item.name === name)?.message ?? ''));
        return problems;
    }

    function markSteps(problems = null) {
        const failing = new Set((problems ?? []).map(item => item.step));
        $$('[data-step-link]').forEach(link => {
            const n = Number(link.dataset.stepLink);
            const done = n < last && fieldsOf(n).every(name => !problem(name));
            link.classList.toggle('is-done', done);
            if (problems) link.classList.toggle('has-error', failing.has(n));
            else if (done) link.classList.remove('has-error');
        });
    }

    function showSummary(problems) {
        summary.hidden = problems.length === 0;
        if (!problems.length) return;
        summary.querySelector('[data-fr-errors-title]').textContent = `Please complete ${problems.length} ${problems.length === 1 ? 'item' : 'items'}:`;
        summary.querySelector('[data-fr-errors-list]').innerHTML = problems.map(item =>
            `<li><a href="#step-${item.step}" data-goto-step="${item.step}" data-focus="${item.name}">Step ${item.step} · ${escapeHtml(config.steps[item.step])}</a>: ${escapeHtml(item.message)}</li>`).join('');
    }

    /* ---------- Review ---------- */

    function renderReview() {
        const value = name => { const input = $(`[name="${name}"]`); return input?.type === 'file' ? input.files?.[0]?.name : input?.value?.trim(); };
        const org = value('org_choice') === 'other' ? value('org_name_other') : value('org_choice');
        const needs = $$('[name="categories[]"]:checked').map(box => box.nextElementSibling.textContent).join(', ');
        const rows = [
            [1, 'Organization', org], [1, 'Contact', [value('contact_person'), value('contact_email'), value('phone')].filter(Boolean).join(' · ')],
            [2, 'Address', $$('[data-address-part]').map(input => input.value.trim()).filter(Boolean).join(', ')],
            [3, 'Kinds of help', needs], [3, 'People', value('beneficiary_count')], [3, 'Purpose', value('purpose')],
            [4, 'Receive through', [config.modes[value('transfer_mode')]?.label, value('provider'), value('account_name'), value('account_number')].filter(Boolean).join(' · ')],
            [5, 'Documents', $$('[data-file-input]').map(input => input.files?.[0]?.name).filter(Boolean).join(', ')],
        ];
        $('[data-fr-review]').innerHTML = rows.map(([step, label, text]) =>
            `<div><dt>${escapeHtml(label)} <a href="#step-${step}" data-goto-step="${step}">Edit</a></dt><dd class="${text ? '' : 'is-missing'}">${escapeHtml(text || 'Missing')}</dd></div>`).join('');
    }

    /* ---------- Events ---------- */

    page.addEventListener('click', event => {
        const link = event.target.closest('[data-goto-step]');
        if (!link) return;
        event.preventDefault();
        show(link.dataset.gotoStep);
        if (link.dataset.focus) setTimeout(() => $(`[data-field="${link.dataset.focus}"] input:not([type=hidden]), [data-field="${link.dataset.focus}"] select, [data-field="${link.dataset.focus}"] textarea`)?.focus(), 350);
    });

    $('[data-fr-prev]').addEventListener('click', () => show(current - 1));
    $('[data-fr-next]').addEventListener('click', () => {
        const problems = check(current);
        if (problems.length) {
            markSteps(problems);
            $(`[data-field="${problems[0].name}"] input, [data-field="${problems[0].name}"] select, [data-field="${problems[0].name}"] textarea`)?.focus();
            return;
        }
        show(current + 1);
    });

    form.addEventListener('change', event => {
        if (event.target.matches('[data-org-choice]')) { syncOrganization(); accountHint(); }
        if (event.target.matches('[data-transfer-mode]')) syncProviders();
        if (event.target.matches('[data-provider]')) syncAccountField();
        if (event.target.matches('[data-file-input]')) {
            const name = event.target.files?.[0]?.name;
            const label = event.target.closest('[data-field]').querySelector('[data-file-name]');
            label.textContent = name ? (name.length > 30 ? `${name.slice(0, 28)}…` : name) : 'Choose a file';
            label.parentElement.classList.toggle('has-file', Boolean(name));
        }
        const field = event.target.closest('[data-field]');
        if (field?.classList.contains('has-error')) setError(field.dataset.field, problem(field.dataset.field));
        markSteps();
    });

    form.addEventListener('input', event => {
        if (event.target.matches('[data-address-part]')) addressPreview();
        if (event.target.matches('[data-account-name], [data-receiver-name]')) accountHint();
        const field = event.target.closest('[data-field]');
        if (field?.classList.contains('has-error')) setError(field.dataset.field, problem(field.dataset.field));
    });

    form.addEventListener('submit', event => {
        const problems = panels.flatMap(panel => check(Number(panel.dataset.step)));
        markSteps(problems);
        showSummary(problems);
        if (problems.length) {
            event.preventDefault();
            summary.focus();
            summary.scrollIntoView({ block: 'start', behavior: 'smooth' });
            return;
        }
        form.querySelector('[type=submit]').disabled = true;
        form.querySelector('[type=submit]').textContent = 'Submitting…';
    });

    syncOrganization();
    syncProviders();
    addressPreview();
    accountHint();
    // The first visit has no #step in the address (a fragment would make the browser jump down).
    const start = location.hash.match(/^#step-(\d)$/)?.[1] ?? config.startStep;
    show(start, { push: false, focus: false, scroll: false });
    if (!summary.hidden) { markSteps(Object.entries(config.fieldStep).filter(([name]) => $(`[data-field="${name}"]`)?.classList.contains('has-error')).map(([name, step]) => ({ name, step }))); }
}
