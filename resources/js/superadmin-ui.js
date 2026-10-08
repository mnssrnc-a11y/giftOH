// Super admin workspace: section navigation (#hash), the fund request list (each opens the full
// request for the final check), account management, price list items, table filters, the running
// price update, and confirmation before settings are saved. All data comes from the server.
const root = document.querySelector('[data-superadmin]');
if (root) {
    const $ = (s, scope = root) => scope.querySelector(s);
    const $$ = (s, scope = root) => [...scope.querySelectorAll(s)];
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(Number(value) || 0);
    const saConfig = window.giftOfHopeSuperadmin ?? {};
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const sections = $$('[data-sa-page]');

    const STATUS = { awaiting: 'Awaiting you', pending: 'With admins', approved: 'Approved', completed: 'Completed', rejected: 'Rejected' };
    const requests = (window.giftOfHopeFinalizationQueue ?? []).map(row => ({
        id: row.id,
        title: row.organization !== '—' ? row.organization : row.project,
        requester: row.requester,
        category: row.category,
        amount: row.amount,
        adminAmount: row.admin_amount,
        granted: row.granted,
        status: STATUS[row.status] ?? row.status,
        stage: row.stage?.label ?? '',
        admin: row.review?.[0]?.by ?? null,
        adminDecision: row.admin_decision,
        url: (saConfig.requestUrl ?? '').replace('__ID__', encodeURIComponent(row.id)),
    }));
    const accounts = saConfig.accounts ?? [];

    const dialog = $('[data-sa-dialog]');
    const content = $('[data-sa-dialog-content]');
    const badge = status => `<span class="sa-badge ${status === 'Awaiting you' ? 'amber' : ['Rejected', 'Disabled'].includes(status) ? 'red' : ['With admins'].includes(status) ? 'gray' : ''}">${escape(status)}</span>`;
    const open = html => { content.innerHTML = html; if (!dialog.open) dialog.showModal(); content.querySelector('input:not([type=hidden]),select,textarea,button')?.focus(); };
    const actions = text => `<div class="sa-actions"><button type="button" class="sa-button secondary" data-sa-close>Cancel</button><button class="sa-button" type="submit">${escape(text)}</button></div>`;
    const empty = columns => `<tr><td colspan="${columns}" class="sa-empty">No matches found. Try another search or filter.</td></tr>`;

    function renderRequests() {
        const preview = $('[data-sa-request-preview]');
        if (preview) preview.innerHTML = requests.filter(r => r.status === 'Awaiting you').slice(0, 5)
            .map(r => `<tr><td><strong>${escape(r.title)}</strong><small>${r.admin ? `Reviewed by ${escape(r.admin)}` : escape(r.stage)}</small></td><td>${money(r.adminAmount ?? r.amount)}</td><td><a class="sa-link" href="${escape(r.url)}">Review ↗</a></td></tr>`).join('')
            || '<tr><td colspan="3" class="sa-empty">Nothing is waiting for your decision.</td></tr>';

        const body = $('[data-sa-requests]');
        if (!body) return;
        const q = ($('[data-sa-request-search]')?.value ?? '').toLowerCase();
        const status = $('[data-sa-request-filter]')?.value ?? '';
        body.innerHTML = requests
            .filter(r => [r.title, r.requester, r.admin, r.category, r.id].join(' ').toLowerCase().includes(q) && (!status || status === r.status))
            .map(r => `<tr><td><a class="sa-row-link" href="${escape(r.url)}"><strong>${escape(r.title)}</strong></a><small>${escape(r.requester)}${r.admin ? ` · ${escape(r.admin)} ${r.adminDecision === 'approved' ? 'recommends approval' : 'recommends rejection'}` : ''}</small></td><td>${escape(r.category)}</td><td>${money(r.amount)}${r.adminAmount != null && r.status === 'Awaiting you' ? `<small>${money(r.adminAmount)} recommended</small>` : ''}${r.granted != null ? `<small>${money(r.granted)} granted</small>` : ''}</td><td>${badge(r.status)}<small>${escape(r.stage)}</small></td><td><a class="sa-link" href="${escape(r.url)}">${r.status === 'Awaiting you' ? 'Review' : 'View'} ↗</a></td></tr>`)
            .join('') || empty(5);
    }

    // Server-rendered tables (accounts, prices, activity log): filter rows by text and data attributes.
    function filterTable(name) {
        const rows = $$(`[data-sa-filter-body="${name}"] tr[data-text]`);
        if (!rows.length) return;
        const q = ($(`[data-sa-filter-search="${name}"]`)?.value ?? '').toLowerCase();
        const selects = $$(`[data-sa-filter-select="${name}"]`);
        let shown = 0;
        rows.forEach(row => {
            const match = row.dataset.text.includes(q) && selects.every(select => !select.value || row.dataset[select.dataset.key] === select.value);
            row.hidden = !match;
            if (match) shown++;
        });
        const emptyNote = $(`[data-sa-filter-empty="${name}"]`);
        if (emptyNote) emptyNote.hidden = shown > 0;
    }

    function setMenu(open) {
        root.classList.toggle('nav-open', open);
        $('[data-sa-menu]')?.setAttribute('aria-expanded', String(open));
    }

    // Sections of the workspace page (#dashboard, #accounts, #prices-food...). Other pages in this
    // layout (a request, settings, notifications) mark their own sidebar item on the server.
    function navigate() {
        setMenu(false);
        if (!sections.length) return;
        const anchor = decodeURIComponent(location.hash.slice(1)) || saConfig.initialSection || '';
        let page = anchor.split('-')[0] || 'dashboard';
        if (!sections.some(el => el.dataset.saPage === page)) page = 'dashboard';
        const changed = sections.find(el => !el.hidden)?.dataset.saPage !== page;
        sections.forEach(el => { el.hidden = el.dataset.saPage !== page; });
        $$('[data-sa-nav]').forEach(el => {
            const current = el.dataset.saNav === page;
            el.toggleAttribute('aria-current', current);
            if (current) el.setAttribute('aria-current', 'page');
            if (current) $('[data-sa-title]').textContent = el.dataset.saLabel;
        });
        document.title = `${$(`[data-sa-nav="${page}"]`)?.dataset.saLabel ?? 'Superadmin'} - Superadmin - Gift of Hope`;
        const target = anchor !== page ? document.getElementById(anchor) : null;
        if (target) target.scrollIntoView({ block: 'start' });
        else if (changed) window.scrollTo({ top: 0 });
    }

    /* ---------- Price list: only the super admin adds, edits or deletes items ---------- */

    const prices = saConfig.prices ?? {};
    const priceGroups = saConfig.priceGroups ?? {};
    const priceForm = (item, groupKey) => {
        const group = item?.group ?? groupKey;
        const groupOptions = Object.entries(priceGroups).map(([key, g]) => `<option value="${escape(key)}" ${key === group ? 'selected' : ''}>${escape(g.label)}</option>`).join('');
        const types = (priceGroups[group]?.types ?? []).map(type => `<option value="${escape(type)}">`).join('');
        return `<div class="sa-fields">
            <label class="sa-field">Group<select name="group" required>${groupOptions}</select></label>
            <label class="sa-field">Type <small>(e.g. Rice (per kilo), Medicine (per piece))</small><input name="type" maxlength="80" list="sa-price-types" value="${escape(item?.type ?? '')}"><datalist id="sa-price-types">${types}</datalist></label>
            <label class="sa-field">Item name<input name="name" maxlength="120" required value="${escape(item?.name ?? '')}"></label>
            <label class="sa-field">Size / unit<input name="size" maxlength="60" required value="${escape(item?.size ?? '')}" placeholder="e.g. per tablet, 1 kg"></label>
            <label class="sa-field full">Budget price (₱)<input type="number" name="price" min="0.01" step="0.01" ${item ? 'required' : ''} value="${item ? escape(item.price) : ''}" placeholder="${item ? '' : 'Leave empty to let the AI find the price by web search'}"><small>${item ? `Reference range ₱${escape(item.min)}–₱${escape(item.max)} · source: ${escape(item.source ?? '—')}` : 'Admins use this price in budgets and cannot change it.'}</small></label>
        </div>`;
    };

    function addPriceItem(groupKey) {
        open(`<h2 id="sa-dialog-title">Add item · ${escape(priceGroups[groupKey]?.label ?? '')}</h2><p>New items are available to admins for budgets right away.</p>
            <form method="POST" action="${escape(saConfig.priceStoreUrl)}" data-price-form><input type="hidden" name="_token" value="${escape(csrf())}"><input type="hidden" name="_section" value="prices-${escape(groupKey)}">${priceForm(null, groupKey)}${actions('Add item')}</form>`);
        $('[data-price-form]', content).addEventListener('submit', event => event.submitter?.setAttribute('disabled', ''));
    }

    function editPriceItem(key) {
        const item = prices[key];
        if (!item) return;
        const url = (saConfig.priceItemUrl ?? '').replace('__KEY__', encodeURIComponent(key));
        open(`<h2 id="sa-dialog-title">Edit item</h2><p>${escape(item.name)} (${escape(item.size)}) · ${escape(item.group_label)}. Budgets already saved keep the price they were saved with.</p>
            <form method="POST" action="${escape(url)}" data-price-form><input type="hidden" name="_token" value="${escape(csrf())}"><input type="hidden" name="_method" value="PUT"><input type="hidden" name="_section" value="prices-${escape(item.group)}">${priceForm(item)}${actions('Save item')}</form>
            <form method="POST" action="${escape(url)}" data-price-delete class="sa-dialog-danger"><input type="hidden" name="_token" value="${escape(csrf())}"><input type="hidden" name="_method" value="DELETE">
                <label class="sa-check"><input type="checkbox" required> Remove ${escape(item.name)} from the price list.</label>
                <div class="sa-actions" style="justify-content:flex-start"><button class="sa-button secondary" type="submit">Delete item</button></div></form>`);
        $$('[data-price-form],[data-price-delete]', content).forEach(form => form.addEventListener('submit', event => event.submitter?.setAttribute('disabled', '')));
    }

    // While the AI price update runs (a background process), check on it and reload when it ends.
    const priceStatus = $('[data-price-status]');
    if (priceStatus?.dataset.running === 'true' && saConfig.priceStatusUrl) {
        const poll = setInterval(async () => {
            try {
                const response = await fetch(saConfig.priceStatusUrl, { headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (!result.running) {
                    clearInterval(poll);
                    priceStatus.textContent = result.status?.state === 'failed' ? 'Update failed' : 'Update finished';
                    location.reload();
                }
            } catch { /* try again on the next tick */ }
        }, 8000);
    }

    function manageAccount(account) {
        const action = (saConfig.accountUrl ?? '').replace('__ID__', encodeURIComponent(account.id));
        open(`<h2 id="sa-dialog-title">Manage account</h2><p>Change the role or turn access on or off. Disabled accounts are signed out and cannot sign in.</p>
            <dl><dt>Name</dt><dd>${escape(account.name)}</dd><dt>Email</dt><dd>${escape(account.email)}</dd><dt>Phone</dt><dd>${escape(account.phone ?? '—')}</dd><dt>Access</dt><dd>${badge(account.active ? 'Enabled' : 'Disabled')}</dd></dl>
            <form method="POST" action="${escape(action)}" data-account-form><input type="hidden" name="_token" value="${escape(csrf())}"><input type="hidden" name="_section" value="accounts">
                <div class="sa-fields"><label class="sa-field">Role<select name="role"><option value="user" ${account.role === 'user' ? 'selected' : ''}>User</option><option value="admin" ${account.role === 'admin' ? 'selected' : ''}>Admin</option></select></label>
                <label class="sa-field">Access<select name="active"><option value="1" ${account.active ? 'selected' : ''}>Enabled</option><option value="0" ${account.active ? '' : 'selected'}>Disabled</option></select></label></div>
                <label class="sa-check"><input type="checkbox" required> I confirm this change to ${escape(account.name)}'s account.</label>${actions('Save changes')}</form>`);
        $('[data-account-form]', content).addEventListener('submit', event => { event.submitter?.setAttribute('disabled', ''); });
    }

    // Forms marked data-sa-confirm ask for confirmation in a dialog before they are submitted.
    $$('form[data-sa-confirm]').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.confirmed === 'true') return;
        event.preventDefault();
        open(`<h2 id="sa-dialog-title">Please confirm</h2><p>${escape(form.dataset.saConfirm)}</p><form data-confirm>${actions('Yes, continue')}</form>`);
        $('[data-confirm]', content).addEventListener('submit', confirmEvent => {
            confirmEvent.preventDefault();
            form.dataset.confirmed = 'true';
            dialog.close();
            form.requestSubmit();
        });
    }));

    root.addEventListener('input', event => {
        if (event.target.matches('[data-sa-request-search]')) renderRequests();
        if (event.target.dataset.saFilterSearch) filterTable(event.target.dataset.saFilterSearch);
    });
    root.addEventListener('change', event => {
        if (event.target.matches('[data-sa-request-filter]')) renderRequests();
        if (event.target.dataset.saFilterSelect) filterTable(event.target.dataset.saFilterSelect);
    });
    window.addEventListener('hashchange', navigate);
    root.addEventListener('click', event => {
        if (event.target.closest('[data-sa-close]')) dialog.close();
        if (event.target.closest('[data-sa-menu]')) setMenu(!root.classList.contains('nav-open'));
        else if (root.classList.contains('nav-open') && !event.target.closest('.ws-sidebar')) setMenu(false);
        const account = event.target.closest('[data-sa-account]');
        if (account) manageAccount(accounts.find(a => a.id === account.dataset.saAccount));
        const addPrice = event.target.closest('[data-sa-price-add]');
        if (addPrice) addPriceItem(addPrice.dataset.saPriceAdd);
        const editPrice = event.target.closest('[data-sa-price-edit]');
        if (editPrice) editPriceItem(editPrice.dataset.saPriceEdit);
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') setMenu(false); });

    // Result of the last action: successes fade after a while, errors stay until dismissed.
    const flash = $('[data-sa-flash]');
    if (flash) {
        flash.querySelector('[data-sa-flash-close]').addEventListener('click', () => flash.remove());
        if (!flash.classList.contains('is-error')) setTimeout(() => flash.remove(), 9000);
    }

    renderRequests();
    navigate();
}
