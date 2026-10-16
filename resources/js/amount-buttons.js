// Quick amounts: under a peso field marked data-amount-buttons, buttons add ₱1,000, ₱500, ₱200 or
// ₱100 to it (or clear it), so most amounts are made without typing. The total shows in pesos under
// the field, and a button that would go over the field's maximum is turned off. Fields added later
// (the admin's decision dialog) get the buttons too. A read-only field (the amount released) can
// only be changed with the buttons.
const STEPS = [1000, 500, 200, 100];
const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function enhance(input) {
    if (input.dataset.amountReady) return;
    input.dataset.amountReady = 'true';

    const bar = document.createElement('div');
    bar.className = 'amount-quick';
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', 'Quick amounts');
    bar.innerHTML = STEPS.map(step => `<button type="button" data-amount-add="${step}" aria-label="Add ${peso.format(step)}">+${step.toLocaleString('en-PH')}</button>`).join('')
        + '<button type="button" class="amount-quick-clear" data-amount-clear aria-label="Clear the amount">Clear</button>'
        + '<output class="amount-quick-total" aria-live="polite"></output>';
    input.insertAdjacentElement('afterend', bar);

    const total = bar.querySelector('output');
    const value = () => { const number = parseFloat(input.value); return Number.isFinite(number) ? number : 0; };
    const max = () => (input.max === '' ? Infinity : parseFloat(input.max));

    const refresh = () => {
        const current = value();
        bar.querySelectorAll('[data-amount-add]').forEach(button => {
            button.disabled = current + Number(button.dataset.amountAdd) > max() + 0.005;
        });
        const over = current > max() + 0.005;
        total.classList.toggle('is-over', over);
        total.textContent = input.value === ''
            ? (input.readOnly ? 'Add the amount with the buttons' : '')
            : `${peso.format(current)}${over ? ` · more than the ${peso.format(max())} allowed` : ''}`;
    };
    const set = amount => {
        input.value = amount;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        refresh();
    };

    bar.addEventListener('click', event => {
        const add = event.target.closest('[data-amount-add]');
        if (add) set(String(Math.round((value() + Number(add.dataset.amountAdd)) * 100) / 100));
        if (event.target.closest('[data-amount-clear]')) { set(''); if (!input.readOnly) input.focus(); }
    });
    input.addEventListener('input', refresh);
    refresh();
}

const enhanceWithin = root => root.querySelectorAll?.('input[data-amount-buttons]').forEach(enhance);
enhanceWithin(document);
new MutationObserver(changes => changes.forEach(change => change.addedNodes.forEach(node => {
    if (node.nodeType !== Node.ELEMENT_NODE) return;
    if (node.matches('input[data-amount-buttons]')) enhance(node);
    enhanceWithin(node);
}))).observe(document.body, { childList: true, subtree: true });
