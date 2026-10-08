import { initializeApp } from "firebase/app";
import { getDatabase, onValue, ref } from "firebase/database";

const fundingRequests = window.giftOfHopeFundingRequests ?? [];
const adminData = window.giftOfHopeAdminData ?? {};

// Latest per-box state from the live Firebase monitor (dashboard tiles, alerts and IoT report).
const liveBoxes = { list: [], connected: false };

const STAGE_TABS = {
    assessment: item => item.stage.step === 1,
    decision: item => item.stage.key === 'ready_for_decision',
    awaiting: item => item.stage.key === 'awaiting_final',
    release: item => item.stage.key === 'to_release',
    liquidation: item => ['released', 'liquidation_review', 'liquidation_returned'].includes(item.stage.key),
    completed: item => item.stage.key === 'completed',
    rejected: item => item.stage.key === 'rejected',
};
const STATUS_LABELS = { awaiting: 'Awaiting super admin', pending: 'Pending', approved: 'Approved', rejected: 'Rejected', completed: 'Completed', cancelled: 'Cancelled' };
const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' })[char]);
const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(Number(value) || 0);
const compactMoney = value => '₱' + new Intl.NumberFormat('en-PH', { notation: 'compact', maximumFractionDigits: 1 }).format(Number(value) || 0);
const formatDate = value => {
    const date = new Date(value);
    return value && !Number.isNaN(date.getTime()) ? date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
};
const timeAgo = value => {
    const time = typeof value === 'number' ? value : new Date(value).getTime();
    if (!time || Number.isNaN(time)) return 'never';
    const seconds = Math.round((Date.now() - time) / 1000);
    if (seconds < 60) return 'just now';
    const units = [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60]];
    const [unit, size] = units.find(([, size]) => seconds >= size);
    const amount = Math.floor(seconds / size);
    return `${amount} ${unit}${amount === 1 ? '' : 's'} ago`;
};
const routeFor = (name, id) => (adminData.routes?.[name] ?? '').replace('__ID__', encodeURIComponent(id));
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function initAdminUI() {
    const app = document.querySelector('[data-admin-app]');
    if (!app) return;

    const state = { status: 'all', category: 'all', query: '', sort: 'newest', page: 1, pageSize: 5 };
    const pageMeta = {
        dashboard: ['Overview', 'Dashboard'],
        updates: ['Communication', 'Updates'],
        funding: ['Management', 'Funding'],
        reports: ['Analytics', 'Reports'],
        settings: ['Account', 'Settings'],
    };
    const modal = app.querySelector('[data-modal]');
    const modalContent = app.querySelector('[data-modal-content]');
    const snackbar = app.querySelector('[data-snackbar-box]');
    let snackTimer;

    const showSnack = message => {
        snackbar.textContent = message;
        snackbar.classList.add('is-visible');
        clearTimeout(snackTimer);
        snackTimer = setTimeout(() => snackbar.classList.remove('is-visible'), 2800);
    };
    const closeModal = () => { modal.hidden = true; modalContent.innerHTML = ''; };
    const openModal = html => { modalContent.innerHTML = html; modal.hidden = false; setTimeout(() => modalContent.querySelector('input:not([type=hidden]),textarea,button,select')?.focus(), 20); };
    const confirmDialog = (title, body, confirmLabel, kind, onConfirm) => {
        openModal(`<h2>${escapeHtml(title)}</h2><p>${body}</p><div class="admin-modal-actions"><button class="admin-button" data-modal-close>Cancel</button><button class="admin-button ${kind}" data-dialog-confirm>${escapeHtml(confirmLabel)}</button></div>`);
        modalContent.querySelector('[data-dialog-confirm]').addEventListener('click', onConfirm, { once: true });
    };

    function navigate(page) {
        // "#reports-prices" opens the Reports page on the Price reference tab.
        const [base, tab] = String(page).split('-');
        if (base === 'reports' && tab) {
            navigate('reports');
            app.querySelector(`[data-report-tab="${tab}"]`)?.click();
            return;
        }
        const safePage = pageMeta[page] ? page : 'dashboard';
        app.querySelectorAll('[data-admin-page]').forEach(node => node.classList.toggle('is-active', node.dataset.adminPage === safePage));
        app.querySelectorAll('[data-admin-nav]').forEach(node => node.classList.toggle('is-active', node.dataset.adminNav === safePage));
        app.querySelector('[data-page-kicker]').textContent = pageMeta[safePage][0];
        app.querySelector('[data-page-title]').textContent = pageMeta[safePage][1];
        app.classList.remove('sidebar-open');
        if (location.hash !== `#${safePage}`) history.replaceState(null, '', `#${safePage}`);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ---------- Funding requests ---------- */

    function filteredFunding() {
        let rows = fundingRequests.filter(item => {
            const haystack = `${item.id} ${item.project} ${item.organization} ${item.requester}`.toLowerCase();
            return (state.status === 'all' || STAGE_TABS[state.status]?.(item))
                && (state.category === 'all' || item.category === state.category)
                && haystack.includes(state.query.toLowerCase());
        });
        if (state.sort === 'amount-desc') rows.sort((a, b) => b.amount - a.amount);
        if (state.sort === 'amount-asc') rows.sort((a, b) => a.amount - b.amount);
        if (state.sort === 'name') rows.sort((a, b) => a.project.localeCompare(b.project));
        return rows;
    }

    function fundingActions(item) {
        return `<a class="admin-table-action" href="${escapeHtml(routeFor('show', item.id))}" title="Open request">⌕</a>`;
    }

    function stageBadge(item) {
        const kind = ['completed', 'to_release', 'released'].includes(item.stage.key) ? 'approved'
            : item.stage.key === 'rejected' ? 'rejected' : item.stage.key === 'awaiting_final' ? 'awaiting' : 'in-progress';
        return `<span class="admin-status ${kind}">${escapeHtml(item.stage.label)}</span>${item.overdue ? '<small style="color:#dc2626;font-weight:700">Overdue</small>' : ''}`;
    }

    function renderFunding() {
        const body = app.querySelector('[data-funding-body]');
        if (!body) return;
        const rows = filteredFunding();
        const pages = Math.max(1, Math.ceil(rows.length / state.pageSize));
        state.page = Math.min(state.page, pages);
        const start = (state.page - 1) * state.pageSize;
        const visible = rows.slice(start, start + state.pageSize);
        body.innerHTML = visible.map(item => `<tr>
            <td><a href="${escapeHtml(routeFor('show', item.id))}"><strong>${escapeHtml(item.project)}</strong></a>${item.unread_messages ? `<a href="${escapeHtml(routeFor('show', item.id))}#messages" class="admin-status in-progress" style="margin:3px 0">✉ ${item.unread_messages} new message${item.unread_messages > 1 ? 's' : ''}</a>` : ''}<small>${escapeHtml(item.id.slice(0, 8))} · ${escapeHtml(item.requester)} · ${item.beneficiary_count || '—'} people</small></td>
            <td>${escapeHtml(item.organization)}</td><td>${escapeHtml(item.category)}</td>
            <td>${item.amount > 0 ? `<strong>${money(item.amount)}</strong>` : '<small>Budget not set</small>'}${item.granted !== null ? `<small>${money(item.granted)} granted</small>` : ''}</td>
            <td>${stageBadge(item)}</td><td>${formatDate(item.date)}</td><td><div class="admin-table-actions">${fundingActions(item)}</div></td>
        </tr>`).join('');
        app.querySelector('[data-funding-table-wrap]').style.display = rows.length ? '' : 'none';
        app.querySelector('[data-funding-empty]').classList.toggle('admin-state-visible', !rows.length);
        app.querySelector('[data-funding-range]').textContent = rows.length ? `Showing ${start + 1}–${Math.min(start + state.pageSize, rows.length)} of ${rows.length}` : 'Showing 0 requests';
        app.querySelector('[data-page-numbers]').innerHTML = Array.from({ length: pages }, (_, index) => `<button class="${state.page === index + 1 ? 'is-active' : ''}" data-funding-page="${index + 1}">${index + 1}</button>`).join('');
        app.querySelector('[data-page-prev]').disabled = state.page === 1;
        app.querySelector('[data-page-next]').disabled = state.page === pages;
    }

    /**
     * Approve / reject form. It posts to the server, which forwards the decision to the super admin.
     */
    function openDecision(id, action) {
        const item = fundingRequests.find(row => row.id === id);
        if (!item || item.status !== 'pending') return;
        const approving = action === 'approved';

        openModal(`<h2>${approving ? 'Approve' : 'Reject'} request</h2>
            <p><strong>${escapeHtml(item.project)}</strong>${item.organization !== item.project ? ` · ${escapeHtml(item.organization)}` : ''} · ${money(item.amount)} requested</p>
            <form method="POST" action="${escapeHtml(routeFor('action', id))}" data-decision-form>
                <input type="hidden" name="_token" value="${escapeHtml(csrfToken())}">
                <input type="hidden" name="action" value="${action}">
                <input type="hidden" name="ai_amount" value="" data-ai-amount>
                ${approving ? `<div class="admin-ai-panel" data-ai-panel><div class="admin-ai-head"><span class="admin-eyebrow">AI assistance</span><strong>Recommended amount</strong></div><div class="admin-loading admin-state-visible" style="padding:14px 0"><div class="admin-spinner"></div>Weighing total funds and priorities…</div></div>
                <div class="admin-field" style="margin-top:14px"><label for="decision-amount">Amount to release (₱)</label><input id="decision-amount" class="admin-input" style="width:100%" type="number" name="amount" min="1" max="${item.amount}" step="0.01" required data-decision-amount></div>` : ''}
                <div class="admin-field" style="margin-top:14px"><label for="decision-notes">${approving ? 'Note for the super admin (optional)' : 'Reason for rejection'}</label><textarea id="decision-notes" class="admin-textarea" style="min-height:80px" name="notes" maxlength="1000" ${approving ? '' : 'required'}></textarea></div>
                <div class="admin-notice" style="margin-top:14px">Your decision is sent to the super admin for finalization. The requester is notified only after the final decision.</div>
                <div class="admin-modal-actions"><button type="button" class="admin-button" data-modal-close>Cancel</button><button class="admin-button ${approving ? 'success' : 'danger'}" type="submit">${approving ? 'Submit approval' : 'Submit rejection'}</button></div>
            </form>`);

        const form = modalContent.querySelector('[data-decision-form]');
        form.addEventListener('submit', () => form.querySelector('[type=submit]').disabled = true);
        if (approving) loadRecommendation(id, form);
    }

    async function loadRecommendation(id, form) {
        const panel = form.querySelector('[data-ai-panel]');
        const amountInput = form.querySelector('[data-decision-amount]');
        try {
            const response = await fetch(routeFor('recommendation', id), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`Request failed (${response.status})`);
            const rec = await response.json();
            if (!panel.isConnected) return;

            if (!rec.available) {
                panel.innerHTML = `<div class="admin-ai-head"><span class="admin-eyebrow">AI assistance</span><strong>No recommendation available</strong></div><p>${escapeHtml(rec.message)}</p>`;
                return;
            }

            form.querySelector('[data-ai-amount]').value = rec.recommended_amount;
            if (!amountInput.value) amountInput.value = rec.recommended_amount;
            const perPerson = rec.per_person !== null && rec.people ? ` · ${money(rec.per_person)} × ${rec.people} people` : '';
            panel.innerHTML = `<div class="admin-ai-head"><span class="admin-eyebrow">AI assistance · ${rec.source === 'ai' ? escapeHtml(rec.provider) : 'priority rules (no AI provider answered)'}</span><strong>${money(rec.recommended_amount)} <small>(${rec.coverage}% of request${perPerson})</small></strong><span class="admin-status ${rec.priority_label === 'critical' ? 'critical' : rec.priority_label === 'high' ? 'approved' : 'in-progress'}">${escapeHtml(rec.priority_label)} priority · ${rec.priority_score}/100</span></div>
                <div class="admin-ai-figures"><span><b>${money(rec.total_funds)}</b>Total funds</span><span><b>${money(rec.committed_funds + rec.reserved_funds)}</b>Committed</span><span><b>${money(rec.available_funds)}</b>Available</span><span><b>${rec.queue_size}</b>In queue</span></div>
                <ul>${rec.reasoning.map(line => `<li>${escapeHtml(line)}</li>`).join('')}</ul>
                <button type="button" class="admin-button" data-use-recommendation>Use ${money(rec.recommended_amount)}</button>
                <p class="admin-field-hint" data-over-budget hidden>This amount is more than the ${money(rec.available_funds)} currently available, which leaves no budget for the other requests.</p>`;
            const warnIfOverBudget = () => { panel.querySelector('[data-over-budget]').hidden = !(Number(amountInput.value) > rec.available_funds); };
            amountInput.addEventListener('input', warnIfOverBudget);
            warnIfOverBudget();
            panel.querySelector('[data-use-recommendation]').addEventListener('click', () => { amountInput.value = rec.recommended_amount; amountInput.focus(); warnIfOverBudget(); });
        } catch (error) {
            console.error('Fund recommendation failed:', error);
            if (panel.isConnected) panel.innerHTML = '<div class="admin-ai-head"><span class="admin-eyebrow">AI assistance</span><strong>Recommendation unavailable</strong></div><p>Enter the amount manually, or close and try again.</p>';
        }
    }

    /* ---------- Charts ---------- */

    function lineChart(container, series, emptyText) {
        if (!container) return;
        const labels = series[0]?.points.map(point => point.label) ?? [];
        const max = Math.max(0, ...series.flatMap(line => line.points.map(point => point.value)));
        if (!labels.length || max === 0) {
            container.innerHTML = `<div class="admin-empty admin-state-visible" style="padding:80px 10px"><strong>${escapeHtml(emptyText)}</strong><p>The chart fills in as records are added.</p></div>`;
            return;
        }
        const [width, height, left, right, top, bottom] = [700, 250, 60, 20, 20, 30];
        const x = index => left + (labels.length === 1 ? 0 : index * (width - left - right) / (labels.length - 1));
        const y = value => top + (1 - value / max) * (height - top - bottom);
        const ticks = [0, 1, 2, 3].map(step => max * step / 3);
        const grid = ticks.map(tick => `<path class="grid" d="M${left} ${y(tick)}H${width - right}"/><text x="${left - 8}" y="${y(tick) + 3}" text-anchor="end">${compactMoney(tick)}</text>`).join('');
        const lines = series.map(line => {
            const points = line.points.map((point, index) => `${x(index)},${y(point.value)}`).join(' ');
            const area = line.area ? `<path class="area" d="M${x(0)} ${y(0)}L${points.replaceAll(' ', 'L')}L${x(labels.length - 1)} ${y(0)}Z"/>` : '';
            const dots = line.points.map((point, index) => `<circle cx="${x(index)}" cy="${y(point.value)}" r="4" style="stroke:${line.color}"><title>${point.label}: ${money(point.value)}</title></circle>`).join('');
            return `${area}<polyline class="line" style="stroke:${line.color}" points="${points}"/>${dots}`;
        }).join('');
        const xLabels = labels.map((label, index) => `<text x="${x(index)}" y="${height - 5}" text-anchor="middle">${escapeHtml(label)}</text>`).join('');
        container.innerHTML = `<svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" role="img"><defs><linearGradient id="adminGradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#64b5f6"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs>${grid}${lines}${xLabels}</svg>`;
    }

    function renderCharts() {
        lineChart(app.querySelector('[data-chart="donations"]'), [{ points: adminData.donationSeries ?? [], color: '#1976d2', area: true }], 'No donations recorded in the last 6 months');
        lineChart(app.querySelector('[data-chart="funding"]'), [
            { points: adminData.fundingSeries?.requested ?? [], color: '#1976d2' },
            { points: adminData.fundingSeries?.granted ?? [], color: '#10b981' },
        ], 'No funding activity in the last 6 months');
    }

    /* ---------- Updates / posts ---------- */

    function compileUpdate() {
        const scope = app.querySelector('[data-compile-scope]').value;
        const now = new Date();
        const scopes = {
            all: ['all charity home requests', () => true],
            pending: ['requests pending admin review', item => item.status === 'pending'],
            awaiting: ['requests awaiting final approval', item => item.status === 'awaiting'],
            approved: ['approved requests', item => ['approved', 'completed'].includes(item.status)],
            month: ['requests submitted this month', item => { const date = new Date(item.date); return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear(); }],
        };
        const [scopeLabel, predicate] = scopes[scope];
        const rows = fundingRequests.filter(predicate);
        if (!rows.length) { showSnack(`There are no ${scopeLabel} to compile.`); return; }

        const total = rows.reduce((sum, item) => sum + item.amount, 0);
        const count = status => rows.filter(item => item.status === status).length;
        const granted = rows.reduce((sum, item) => sum + (item.granted ?? 0), 0);
        const byCategory = Object.entries(rows.reduce((groups, item) => {
            groups[item.category] ??= { count: 0, amount: 0 };
            groups[item.category].count++;
            groups[item.category].amount += item.amount;
            return groups;
        }, {})).sort((a, b) => b[1].amount - a[1].amount);
        const listed = rows.slice(0, 30);

        const body = [
            `Here is a compiled update on ${scopeLabel} as of ${formatDate(now)}.`,
            '',
            `Total: ${rows.length} request(s) · ${money(total)} requested`,
            `• Pending admin review: ${count('pending')}`,
            `• Awaiting final approval: ${count('awaiting')}`,
            `• Approved: ${count('approved') + count('completed')}${granted ? ` (${money(granted)} granted)` : ''}`,
            `• Rejected: ${count('rejected')}`,
            '',
            'By category:',
            ...byCategory.map(([category, group]) => `• ${category} — ${group.count} request(s), ${money(group.amount)}`),
            '',
            'Charity homes:',
            ...listed.map((item, index) => `${index + 1}. ${item.organization} (${item.category}) — ${money(item.amount)} requested · ${STATUS_LABELS[item.status] ?? item.status}${item.granted !== null ? ` · ${money(item.granted)} granted` : ''}`),
            ...(rows.length > listed.length ? [`…and ${rows.length - listed.length} more.`] : []),
        ].join('\n');

        const bodyField = app.querySelector('[data-post-body]');
        const apply = () => {
            app.querySelector('[data-post-type]').value = 'compiled_report';
            app.querySelector('[data-post-title]').value = `Funding update — ${now.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })}`;
            bodyField.value = body;
            closeModal();
            bodyField.focus();
            showSnack('Compiled update ready. Review it before publishing.');
        };
        if (bodyField.value.trim()) confirmDialog('Replace your draft?', 'The message box already has text. Generating a compiled update will replace it.', 'Replace draft', 'warning', apply);
        else apply();
    }

    /* ---------- Reports ---------- */

    function applyReportFilters() {
        const query = (app.querySelector('[data-report-search]')?.value ?? '').toLowerCase();
        const from = app.querySelector('[data-report-from]')?.value;
        const to = app.querySelector('[data-report-to]')?.value;
        app.querySelectorAll('.admin-report-panel tbody tr').forEach(row => {
            if (row.hasAttribute('data-empty')) return;
            const date = (row.dataset.date ?? '').slice(0, 10);
            const inRange = !date || ((!from || date >= from) && (!to || date <= to));
            row.style.display = row.textContent.toLowerCase().includes(query) && inRange ? '' : 'none';
        });
    }

    function exportActiveReport() {
        const panel = app.querySelector('.admin-report-panel.is-active');
        const table = panel?.querySelector('table');
        if (!table) return;
        const cell = text => `"${text.trim().replace(/\s+/g, ' ').replace(/"/g, '""')}"`;
        const rows = [[...table.querySelectorAll('thead th')].map(th => cell(th.textContent))]
            .concat([...table.querySelectorAll('tbody tr')].filter(row => row.style.display !== 'none' && !row.hasAttribute('data-empty')).map(row => [...row.cells].map(td => cell(td.textContent))));
        if (rows.length === 1) { showSnack('There are no rows to export.'); return; }
        const blob = new Blob(['﻿' + rows.map(row => row.join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: `giftofhope-${panel.dataset.reportPanel}-report-${new Date().toISOString().slice(0, 10)}.csv` });
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        showSnack(`Exported ${rows.length - 1} row(s).`);
    }

    /* ---------- Events ---------- */

    app.addEventListener('click', event => {
        const target = event.target.closest('button,a');
        if (!target) return;
        if (target.matches('[data-sidebar-toggle]')) app.classList.toggle('sidebar-open');
        if (target.dataset.adminGo) navigate(target.dataset.adminGo);
        if (target.dataset.adminNav && app.querySelector('[data-admin-page]')) { event.preventDefault(); navigate(target.dataset.adminNav); }
        if (target.dataset.snackbar) showSnack(target.dataset.snackbar);
        if (target.matches('[data-reload]')) location.reload();
        if (target.matches('[data-theme-toggle]')) setDark(!app.classList.contains('is-dark'));
        if (target.matches('[data-modal-close]')) closeModal();
        if (target.dataset.statusTab) { state.status = target.dataset.statusTab; state.page = 1; app.querySelectorAll('[data-status-tab]').forEach(tab => tab.classList.toggle('is-active', tab === target)); renderFunding(); }
        if (target.dataset.fundingPage) { state.page = Number(target.dataset.fundingPage); renderFunding(); }
        if (target.matches('[data-page-prev]') && state.page > 1) { state.page--; renderFunding(); }
        if (target.matches('[data-page-next]') && state.page < Math.ceil(filteredFunding().length / state.pageSize)) { state.page++; renderFunding(); }
        if (target.dataset.decisionOpen) openDecision(target.dataset.requestId, target.dataset.decisionOpen);
        if (target.matches('[data-compile-post]')) compileUpdate();
        if (target.dataset.reportTab) { app.querySelectorAll('[data-report-tab]').forEach(tab => tab.classList.toggle('is-active', tab === target)); app.querySelectorAll('[data-report-panel]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.reportPanel === target.dataset.reportTab)); }
        if (target.matches('[data-report-clear]')) { app.querySelectorAll('[data-report-search],[data-report-from],[data-report-to]').forEach(input => input.value = ''); applyReportFilters(); }
        if (target.matches('[data-export-csv]')) exportActiveReport();
        if (target.matches('[data-print]')) window.print();
        if (target.dataset.settingsTab) { app.querySelectorAll('[data-settings-tab]').forEach(tab => tab.classList.toggle('is-active', tab === target)); app.querySelectorAll('[data-settings-panel]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.settingsPanel === target.dataset.settingsTab)); }
        if (target.matches('[data-profile-upload]')) app.querySelector('[data-profile-input]').click();
    });

    app.addEventListener('input', event => {
        if (event.target.matches('[data-funding-search]')) { state.query = event.target.value; state.page = 1; renderFunding(); }
        if (event.target.matches('[data-report-search]')) applyReportFilters();
        if (event.target.matches('[data-global-search]') && event.target.value.length > 2) {
            const query = event.target.value.toLowerCase();
            const page = ['funding', 'request', 'project'].some(term => query.includes(term)) ? 'funding'
                : ['update', 'post', 'announce'].some(term => query.includes(term)) ? 'updates'
                : ['report', 'iot'].some(term => query.includes(term)) ? 'reports'
                : ['setting', 'profile', 'security'].some(term => query.includes(term)) ? 'settings' : null;
            if (page && app.querySelector('[data-admin-page]')) navigate(page);
        }
    });

    app.addEventListener('change', event => {
        if (event.target.matches('[data-funding-category]')) { state.category = event.target.value; state.page = 1; renderFunding(); }
        if (event.target.matches('[data-funding-sort]')) { state.sort = event.target.value; state.page = 1; renderFunding(); }
        if (event.target.matches('[data-report-from],[data-report-to]')) applyReportFilters();
        if (event.target.matches('[data-dark-switch]')) setDark(event.target.checked);
        if (event.target.matches('[data-compact-switch]')) {
            app.classList.toggle('is-compact', event.target.checked);
            try { localStorage.setItem('giftOfHopeAdminDensity', event.target.checked ? 'compact' : 'comfortable'); } catch { /* storage unavailable */ }
            showSnack(`Table density changed to ${event.target.checked ? 'compact' : 'comfortable'}.`);
        }
        if (event.target.matches('[data-profile-input]')) previewProfilePhoto(event.target);
        if (event.target.matches('[data-post-image]')) previewPostImage(event.target);
        if (event.target.matches('[data-two-factor-switch]')) {
            const toggle = event.target;
            const enabling = toggle.checked;
            toggle.checked = !enabling;
            confirmDialog(enabling ? 'Turn on email verification?' : 'Turn off email verification?',
                enabling ? 'You will enter an emailed code at sign-in and before each funding decision is submitted.' : 'Sign-in and funding decisions will no longer require an emailed code.',
                enabling ? 'Turn on' : 'Turn off', enabling ? 'primary' : 'warning',
                async () => {
                    closeModal();
                    toggle.disabled = true;
                    try {
                        // Saved in the background; the page does not reload.
                        const response = await fetch(toggle.form.action, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                            body: JSON.stringify({ email_notifications: enabling ? 1 : 0 }),
                        });
                        const result = await response.json().catch(() => ({}));
                        if (!response.ok || !result.saved) throw new Error(result.message || 'Email verification could not be changed. Try again.');
                        toggle.checked = enabling;
                        showSnack(result.message);
                    } catch (error) {
                        toggle.checked = !enabling;
                        showSnack(error.message);
                    } finally {
                        toggle.disabled = false;
                    }
                });
        }
    });

    // Validate and preview a new profile photo locally; it is saved only when the admin clicks Save.
    function previewProfilePhoto(input) {
        const file = input.files?.[0];
        const saveButton = app.querySelector('[data-profile-save]');
        const hint = app.querySelector('[data-profile-hint]');
        if (!file) return;
        const problem = !['image/jpeg', 'image/png'].includes(file.type) ? 'The photo must be a JPG or PNG file.'
            : file.size > 5 * 1024 * 1024 ? 'The photo must not be larger than 5 MB.' : null;
        if (problem) {
            input.value = '';
            saveButton.hidden = true;
            hint.textContent = problem;
            showSnack(problem);
            return;
        }
        const photo = app.querySelector('[data-profile-photo]');
        const url = URL.createObjectURL(file);
        photo.style.background = `center/cover url("${url}")`;
        photo.querySelector('[data-profile-initial]').hidden = true;
        saveButton.hidden = false;
        hint.textContent = `${file.name} selected. Click “Save photo” to update your profile.`;
    }

    // Photo for a new update: checked and previewed before it is posted.
    function previewPostImage(input) {
        const file = input.files?.[0];
        const preview = app.querySelector('[data-post-image-preview]');
        if (!file) { preview.hidden = true; return; }
        const problem = !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ? 'The photo must be a JPG, PNG or WebP image.'
            : file.size > 5 * 1024 * 1024 ? 'The photo must not be larger than 5 MB.' : null;
        if (problem) { input.value = ''; preview.hidden = true; showSnack(problem); return; }
        preview.src = URL.createObjectURL(file);
        preview.hidden = false;
    }

    app.querySelector('[data-profile-form]')?.addEventListener('submit', event => {
        event.submitter?.setAttribute('disabled', '');
        showSnack('Uploading photo…');
    });

    app.querySelectorAll('[data-delete-post]').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.confirmed === 'true') return;
        event.preventDefault();
        confirmDialog('Delete this update?', 'It will be removed from the landing page and every user page.', 'Delete', 'danger', () => { form.dataset.confirmed = 'true'; form.submit(); });
    }));

    function setDark(enabled) {
        app.classList.toggle('is-dark', enabled);
        app.querySelectorAll('[data-dark-switch]').forEach(toggle => toggle.checked = enabled);
        try { localStorage.setItem('giftOfHopeAdminTheme', enabled ? 'dark' : 'light'); } catch { /* storage unavailable */ }
    }

    document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeModal(); app.classList.remove('sidebar-open'); } });
    let storedTheme = null;
    let storedDensity = null;
    try { storedTheme = localStorage.getItem('giftOfHopeAdminTheme'); storedDensity = localStorage.getItem('giftOfHopeAdminDensity'); } catch { /* storage unavailable */ }
    setDark(storedTheme === 'dark');
    app.classList.toggle('is-compact', storedDensity === 'compact');
    app.querySelectorAll('[data-compact-switch]').forEach(toggle => toggle.checked = storedDensity === 'compact');

    if (app.querySelectorAll('[data-admin-page]').length > 0) {
        window.addEventListener('hashchange', () => navigate(location.hash.slice(1)));
        navigate(adminData.initialPage || location.hash.slice(1) || 'dashboard');
        if (adminData.initialPage === 'settings') app.querySelector('[data-settings-tab="profile"]')?.click();
        renderFunding();
        renderCharts();
    }
    initBudgetBuilder(app);
    initAdminIotMonitor(app, () => applyReportFilters());
}

/**
 * Per-person budget builder on the admin request page. Items come from the AI-maintained price
 * list and their prices are fixed: the admin only chooses items and quantities.
 */
function initBudgetBuilder(app) {
    const form = app.querySelector('[data-budget-builder]');
    const config = adminData.budget;
    if (!form || !config) return;

    const catalog = Object.fromEntries(Object.values(config.catalog).flat().map(item => [item.key, item]));
    // Only price-list items can be budgeted (older custom lines are dropped).
    const rows = (config.rows ?? []).filter(row => catalog[row.ref]).map(row => ({ ref: row.ref, qty: row.qty }));
    const updated = item => item.updated_at ? `updated ${new Date(item.updated_at).toLocaleDateString('en-PH', { month: 'short', year: 'numeric' })}` : 'initial reference';
    const subtotal = row => (Number(row.qty) || 0) * (catalog[row.ref]?.price || 0);

    function render() {
        form.querySelector('[data-budget-rows]').innerHTML = rows.length ? rows.map((row, index) => {
            const item = catalog[row.ref];
            return `<tr data-budget-row="${index}">
                <td><input type="hidden" name="budget[${index}][ref]" value="${escapeHtml(row.ref)}"><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.size)} · ${escapeHtml(item.source || 'price list')}</small></td>
                <td><input class="admin-input" style="width:100%" type="number" min="0.25" step="0.25" required name="budget[${index}][qty]" value="${escapeHtml(row.qty)}" data-budget-field="qty" aria-label="Quantity per person"></td>
                <td class="admin-price-fixed">${money(item.price)}<small>${escapeHtml(updated(item))}</small></td>
                <td data-budget-subtotal>${money(subtotal(row))}</td>
                <td><button type="button" class="admin-table-action" data-budget-remove="${index}" aria-label="Remove item">×</button></td></tr>`;
        }).join('') : '<tr><td colspan="5" style="text-align:center;color:var(--admin-muted)">No items yet. Add items for one person\'s package.</td></tr>';
        totals();
    }

    function totals() {
        const perPerson = rows.reduce((sum, row) => sum + subtotal(row), 0);
        form.querySelector('[data-budget-per-person]').textContent = money(perPerson);
        form.querySelector('[data-budget-total]').textContent = money(perPerson * (config.people || 0));
        const capNote = form.querySelector('[data-budget-cap-note]');
        if (capNote) capNote.textContent = config.cap && perPerson > config.cap ? `This package is ${money(perPerson - config.cap)} above the usual allocation.` : '';
    }

    form.querySelector('[data-budget-picker]').insertAdjacentHTML('beforeend', Object.entries(config.catalog).map(([group, items]) =>
        `<optgroup label="${escapeHtml(group)}">${items.map(item => `<option value="${escapeHtml(item.key)}">${escapeHtml(`${item.name} (${item.size}) · ${money(item.price)}`)}</option>`).join('')}</optgroup>`).join(''));

    form.addEventListener('change', event => {
        if (!event.target.matches('[data-budget-picker]') || !event.target.value) return;
        const existing = rows.find(row => row.ref === event.target.value);
        if (existing) existing.qty = Number(existing.qty) + 1;
        else rows.push({ ref: event.target.value, qty: 1 });
        event.target.value = '';
        render();
    });
    form.addEventListener('input', event => {
        if (event.target.dataset.budgetField !== 'qty') return;
        const tr = event.target.closest('[data-budget-row]');
        const row = rows[Number(tr.dataset.budgetRow)];
        row.qty = event.target.value;
        // Update in place so typing keeps focus.
        tr.querySelector('[data-budget-subtotal]').textContent = money(subtotal(row));
        totals();
    });
    form.addEventListener('click', event => {
        if (event.target.dataset.budgetRemove !== undefined) {
            rows.splice(Number(event.target.dataset.budgetRemove), 1);
            render();
        }
    });
    form.addEventListener('submit', event => {
        if (rows.length === 0) { event.preventDefault(); alert('Add at least one item to the per-person budget.'); }
    });

    render();
}

/**
 * Live smart-box telemetry: stat card, status grid, alerts, and the IoT report.
 */
function initAdminIotMonitor(app, onRender) {
    const monitor = app.querySelector('[data-admin-iot-monitor]');
    if (!monitor) return;

    const onlineLabel = monitor.querySelector('[data-admin-iot-online-label]');
    const totalLabel = monitor.querySelector('[data-admin-iot-total]');
    const boxGrid = app.querySelector('[data-admin-iot-boxes]');
    const alertList = app.querySelector('[data-admin-iot-alerts]');
    const summaryBadge = app.querySelector('[data-admin-iot-summary]');
    const reportBody = app.querySelector('[data-report-iot-body]');
    const reportStat = key => app.querySelector(`[data-report-iot="${key}"]`);
    const trackedBoxes = new Map();
    const missedChecksBeforeOffline = 4;
    // Boxes report lastSeen in epoch seconds; normalise to milliseconds.
    const toMillis = value => { const number = Number(value || 0); return number > 0 && number < 1e12 ? number * 1000 : number; };
    let latestBoxes = null;

    const renderUnavailable = () => {
        onlineLabel.textContent = 'Live status unavailable';
        const message = '<div class="admin-empty admin-state-visible" style="padding:30px 10px"><strong>Live box data is unavailable</strong><p>Firebase could not be reached.</p></div>';
        if (alertList) alertList.innerHTML = message;
        if (boxGrid) boxGrid.innerHTML = message;
        if (summaryBadge) { summaryBadge.textContent = 'Unavailable'; summaryBadge.className = 'admin-status offline'; }
        if (reportBody) reportBody.innerHTML = '<tr data-empty><td colspan="6">Live box data is unavailable.</td></tr>';
    };

    const render = boxes => {
        const boxIds = Object.keys(boxes || {});
        liveBoxes.connected = true;
        liveBoxes.list = boxIds.map(boxId => {
            const box = boxes[boxId] || {};
            const heartbeat = Number(box.heartbeat || 0);
            const lastSeen = toMillis(box.lastSeen);
            const previous = trackedBoxes.get(boxId);
            let online = false;

            if (heartbeat === 0) {
                trackedBoxes.set(boxId, { heartbeat: 0, missedChecks: 0, online: false });
            } else if (!previous) {
                online = lastSeen > 0 && (Date.now() - lastSeen) <= 12000;
                trackedBoxes.set(boxId, { heartbeat, missedChecks: online ? 0 : missedChecksBeforeOffline, online });
            } else {
                const heartbeatChanged = heartbeat !== previous.heartbeat;
                const missedChecks = heartbeatChanged ? 0 : previous.missedChecks + 1;
                online = heartbeatChanged || (previous.online && missedChecks < missedChecksBeforeOffline);
                trackedBoxes.set(boxId, { heartbeat, missedChecks, online });
            }

            return { id: boxId, location: box.location || 'Unknown location', total: Number(box.total || 0), coins: Number(box.totalCoins || 0), lastSeen, online };
        }).sort((a, b) => a.id.localeCompare(b.id));
        trackedBoxes.forEach((_, boxId) => { if (!boxIds.includes(boxId)) trackedBoxes.delete(boxId); });

        const list = liveBoxes.list;
        const online = list.filter(box => box.online).length;
        const offline = list.length - online;
        onlineLabel.textContent = `${online} online`;
        totalLabel.textContent = list.length;

        if (summaryBadge) {
            summaryBadge.textContent = list.length ? `${online} of ${list.length} online` : 'No boxes';
            summaryBadge.className = `admin-status ${offline === 0 && list.length ? 'online' : offline ? 'offline' : ''}`;
        }
        if (boxGrid) {
            boxGrid.innerHTML = list.length
                ? list.map(box => `<div class="admin-box"><div class="admin-box-head"><div><strong>${escapeHtml(box.id)}</strong><br><small>${escapeHtml(box.location)}</small></div><span class="admin-status ${box.online ? 'online' : 'offline'}">${box.online ? 'Online' : 'Offline'}</span></div><p><span>Collected</span><b>${money(box.total)}</b></p><p><span>Coins counted</span><b>${box.coins}</b></p><p><span>Last seen</span><b>${timeAgo(box.lastSeen)}</b></p></div>`).join('')
                : '<div class="admin-empty admin-state-visible" style="padding:30px 10px;grid-column:1/-1"><strong>No smart boxes registered</strong></div>';
        }
        if (alertList) {
            const alerts = list.filter(box => !box.online);
            alertList.innerHTML = alerts.length
                ? alerts.map(box => `<div class="admin-list-row"><div class="admin-list-icon" style="color:#dc2626">!</div><div class="admin-list-copy"><strong>Box offline</strong><span>${escapeHtml(box.id)} · ${escapeHtml(box.location)} · last seen ${timeAgo(box.lastSeen)}</span></div><div class="admin-list-meta"><span class="admin-status critical">Offline</span></div></div>`).join('')
                : `<div class="admin-empty admin-state-visible" style="padding:30px 10px"><strong>${list.length ? 'All boxes are online' : 'No smart boxes registered'}</strong><p>${list.length ? 'No alerts need attention.' : ''}</p></div>`;
        }
        if (reportBody) {
            reportBody.innerHTML = list.length
                ? list.map(box => `<tr><td><strong>${escapeHtml(box.id)}</strong></td><td>${escapeHtml(box.location)}</td><td>${money(box.total)}</td><td>${box.coins}</td><td><span class="admin-status ${box.online ? 'online' : 'offline'}">${box.online ? 'Online' : 'Offline'}</span></td><td>${timeAgo(box.lastSeen)}</td></tr>`).join('')
                : '<tr data-empty><td colspan="6">No smart boxes registered.</td></tr>';
            reportStat('total').textContent = list.length;
            reportStat('online').textContent = `${online} currently online`;
            reportStat('collected').textContent = money(list.reduce((sum, box) => sum + box.total, 0));
            reportStat('coins').textContent = list.reduce((sum, box) => sum + box.coins, 0).toLocaleString('en-PH');
            reportStat('offline').textContent = offline;
        }
        onRender?.();
    };

    (window.giftOfHopeFirebaseConfig ? Promise.resolve(window.giftOfHopeFirebaseConfig) : fetch('/config/firebase')
        .then(response => {
            if (!response.ok) throw new Error(`Firebase configuration request failed (${response.status})`);
            return response.json();
        }))
        .then(firebaseConfig => {
            const database = getDatabase(initializeApp(firebaseConfig, 'admin-iot-monitor'));
            onValue(ref(database, '/boxes'), snapshot => {
                latestBoxes = snapshot.val();
                render(latestBoxes);
            }, error => {
                console.error('Admin Firebase read failed:', error);
                renderUnavailable();
            });
        })
        .catch(error => {
            console.error('Admin Firebase monitor initialization failed:', error);
            renderUnavailable();
        });

    setInterval(() => { if (latestBoxes !== null) render(latestBoxes); }, 3000);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAdminUI);
else initAdminUI();
