import { initializeApp } from "firebase/app";
import { getDatabase, onValue, ref } from "firebase/database";

const fundingRequests = [
    { id: 'FR-2026-036', project: 'Community Learning Hub', organization: 'Bayanihan Foundation', category: 'Education', requester: 'Maria Santos', amount: 85000, status: 'pending', date: 'Jul 29, 2026', description: 'Equip a shared learning center with books, computers, and reliable connectivity for 180 students.', reason: 'Students in the area travel over eight kilometers to access digital learning resources.' },
    { id: 'FR-2026-035', project: 'Rural Medical Mission', organization: 'CareBridge PH', category: 'Healthcare', requester: 'Dr. Paolo Reyes', amount: 120000, status: 'approved', date: 'Jul 27, 2026', description: 'A three-day medical mission providing consultations, medicine, and diagnostic screening.', reason: 'Two remote communities have limited access to primary healthcare.' },
    { id: 'FR-2026-034', project: 'Nutrition Support Program', organization: 'HopeWorks', category: 'Food & Shelter', requester: 'Ana Cruz', amount: 48500, status: 'pending', date: 'Jul 25, 2026', description: 'Provide nutritious meal packs to children and nursing mothers for twelve weeks.', reason: 'Local health workers documented rising nutrition risks.' },
    { id: 'FR-2026-033', project: 'Flood Recovery Kits', organization: 'Tulong Kababayan', category: 'Community', requester: 'Ramon Lim', amount: 96000, status: 'completed', date: 'Jul 21, 2026', description: 'Distribute sanitation, bedding, and essential household kits to flood-affected families.', reason: 'Families returned to homes without basic household supplies.' },
    { id: 'FR-2026-032', project: 'School Kit Drive', organization: 'Bright Futures', category: 'Education', requester: 'Lea Gomez', amount: 65000, status: 'completed', date: 'Jul 18, 2026', description: 'Supply notebooks, learning materials, and uniforms to public school students.', reason: 'Many enrolled learners begin the year without complete supplies.' },
    { id: 'FR-2026-031', project: 'Mobile Dental Clinic', organization: 'Smile PH', category: 'Healthcare', requester: 'Mark Villanueva', amount: 78000, status: 'rejected', date: 'Jul 15, 2026', description: 'Operate a mobile dental clinic for two underserved barangays.', reason: 'Preventive dental care is not locally available.' },
    { id: 'FR-2026-030', project: 'Community Pantry Restock', organization: 'Shared Table', category: 'Food & Shelter', requester: 'Nina Flores', amount: 32000, status: 'cancelled', date: 'Jul 12, 2026', description: 'Restock shelf-stable food and hygiene products in a community pantry.', reason: 'Pantry inventory is below its safe weekly level.' },
    { id: 'FR-2026-029', project: 'Women Livelihood Starter Kits', organization: 'Gawa Natin', category: 'Community', requester: 'Jessa Tan', amount: 110000, status: 'approved', date: 'Jul 10, 2026', description: 'Starter tools and mentoring for home-based livelihood groups.', reason: 'Participants completed training but lack production tools.' },
    { id: 'FR-2026-028', project: 'Reading Corners', organization: 'BookBridge', category: 'Education', requester: 'Carlo Diaz', amount: 44000, status: 'pending', date: 'Jul 8, 2026', description: 'Create reading corners in four daycare centers.', reason: 'The centers do not have age-appropriate books or shelving.' },
    { id: 'FR-2026-027', project: 'Emergency Shelter Repair', organization: 'Safe Roof', category: 'Food & Shelter', requester: 'Mila Ramos', amount: 135000, status: 'approved', date: 'Jul 5, 2026', description: 'Repair roofing and water damage in an emergency family shelter.', reason: 'Recent storms damaged sleeping areas and storage rooms.' },
    { id: 'FR-2026-026', project: 'Community First Aid Training', organization: 'Ready Barangay', category: 'Healthcare', requester: 'Joel Ong', amount: 38000, status: 'rejected', date: 'Jul 2, 2026', description: 'Train community volunteers and provide first aid kits.', reason: 'Volunteer responders need refreshed emergency skills.' },
    { id: 'FR-2026-025', project: 'Clean Water Access', organization: 'Water for All', category: 'Community', requester: 'Lara Mendoza', amount: 140000, status: 'completed', date: 'Jun 28, 2026', description: 'Install shared filtration and storage for a rural community.', reason: 'The current water source fails basic quality tests.' },
];

function initAdminUI() {
    const app = document.querySelector('[data-admin-app]');
    if (!app) return;

    const state = { status: 'all', category: 'all', query: '', sort: 'newest', page: 1, pageSize: 5 };
    const pageMeta = {
        dashboard: ['Overview', 'Dashboard'],
        funding: ['Management', 'Funding'],
        reports: ['Analytics', 'Reports'],
        settings: ['Account', 'Settings'],
    };
    const modal = app.querySelector('[data-modal]');
    const modalContent = app.querySelector('[data-modal-content]');
    const snackbar = app.querySelector('[data-snackbar-box]');
    let snackTimer;

    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' })[char]);
    const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(value);
    const showSnack = message => {
        snackbar.textContent = message;
        snackbar.classList.add('is-visible');
        clearTimeout(snackTimer);
        snackTimer = setTimeout(() => snackbar.classList.remove('is-visible'), 2800);
    };
    const closeModal = () => { modal.hidden = true; modalContent.innerHTML = ''; };
    const openModal = html => { modalContent.innerHTML = html; modal.hidden = false; setTimeout(() => modalContent.querySelector('input,button,select')?.focus(), 20); };

    function navigate(page) {
        const safePage = pageMeta[page] ? page : 'dashboard';
        app.querySelectorAll('[data-admin-page]').forEach(node => node.classList.toggle('is-active', node.dataset.adminPage === safePage));
        app.querySelectorAll('[data-admin-nav]').forEach(node => node.classList.toggle('is-active', node.dataset.adminNav === safePage));
        app.querySelector('[data-page-kicker]').textContent = pageMeta[safePage][0];
        app.querySelector('[data-page-title]').textContent = pageMeta[safePage][1];
        app.classList.remove('sidebar-open');
        if (location.hash !== `#${safePage}`) history.replaceState(null, '', `#${safePage}`);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function filteredFunding() {
        let rows = fundingRequests.filter(item => {
            const haystack = `${item.id} ${item.project} ${item.organization} ${item.requester}`.toLowerCase();
            return (state.status === 'all' || item.status === state.status)
                && (state.category === 'all' || item.category === state.category)
                && haystack.includes(state.query.toLowerCase());
        });
        if (state.sort === 'amount-desc') rows.sort((a,b) => b.amount - a.amount);
        if (state.sort === 'amount-asc') rows.sort((a,b) => a.amount - b.amount);
        if (state.sort === 'name') rows.sort((a,b) => a.project.localeCompare(b.project));
        return rows;
    }

    function fundingActions(item) {
        const options = [];
        if (item.status === 'pending') options.push(['approve','✓','Approve'], ['reject','×','Reject'], ['cancel','−','Cancel']);
        if (item.status === 'approved') options.push(['complete','✓','Complete'], ['cancel','−','Cancel']);
        return `<button class="admin-table-action" data-request-view="${item.id}" title="View details">⌕</button>${options.slice(0,2).map(([action,icon,label]) => `<button class="admin-table-action" data-request-action="${action}" data-request-id="${item.id}" title="${label}">${icon}</button>`).join('')}`;
    }

    function renderFunding() {
        const rows = filteredFunding();
        const pages = Math.max(1, Math.ceil(rows.length / state.pageSize));
        state.page = Math.min(state.page, pages);
        const start = (state.page - 1) * state.pageSize;
        const visible = rows.slice(start, start + state.pageSize);
        app.querySelector('[data-funding-body]').innerHTML = visible.map(item => `<tr>
            <td><strong>${escapeHtml(item.project)}</strong><small>${item.id} · ${escapeHtml(item.requester)}</small></td>
            <td>${escapeHtml(item.organization)}</td><td>${escapeHtml(item.category)}</td><td><strong>${money(item.amount)}</strong></td>
            <td><span class="admin-status ${item.status}">${item.status}</span></td><td>${item.date}</td><td><div class="admin-table-actions">${fundingActions(item)}</div></td>
        </tr>`).join('');
        app.querySelector('[data-funding-table-wrap]').style.display = rows.length ? '' : 'none';
        app.querySelector('[data-funding-empty]').classList.toggle('admin-state-visible', !rows.length);
        app.querySelector('[data-funding-range]').textContent = rows.length ? `Showing ${start + 1}–${Math.min(start + state.pageSize, rows.length)} of ${rows.length}` : 'Showing 0 requests';
        app.querySelector('[data-page-numbers]').innerHTML = Array.from({length: pages}, (_, index) => `<button class="${state.page === index + 1 ? 'is-active' : ''}" data-funding-page="${index + 1}">${index + 1}</button>`).join('');
        app.querySelector('[data-page-prev]').disabled = state.page === 1;
        const pendingBadge = app.querySelector('[data-pending-count]');
        if (pendingBadge && !pendingBadge.textContent.trim()) {
            pendingBadge.textContent = fundingRequests.filter(item => item.status === 'pending').length;
        }
    }

    function openRequestDetails(id) {
        const item = fundingRequests.find(row => row.id === id);
        if (!item) return;
        const allowed = item.status === 'pending'
            ? [['approve','Approve','success'],['reject','Reject','danger'],['cancel','Cancel','warning']]
            : item.status === 'approved' ? [['complete','Mark as completed','success'],['cancel','Cancel','warning']] : [];
        openModal(`<h2>${escapeHtml(item.project)}</h2><p>${item.id} · Submitted by ${escapeHtml(item.requester)}</p>
            <div class="admin-grid admin-form-grid" style="margin:18px 0"><div class="admin-field"><label>Organization</label><strong>${escapeHtml(item.organization)}</strong></div><div class="admin-field"><label>Requested amount</label><strong>${money(item.amount)}</strong></div><div class="admin-field"><label>Category</label><span>${escapeHtml(item.category)}</span></div><div class="admin-field"><label>Status</label><span class="admin-status ${item.status}">${item.status}</span></div></div>
            <div class="admin-field"><label>Description</label><p>${escapeHtml(item.description)}</p></div><div class="admin-field"><label>Reason</label><p>${escapeHtml(item.reason)}</p></div>
            <div class="admin-history"><h3 style="font-size:13px">Funding history</h3><div class="admin-timeline-row"><strong>Request submitted</strong><span>${item.date} · ${escapeHtml(item.requester)}</span></div><div class="admin-timeline-row"><strong>Documents reviewed</strong><span>${item.date} · Mock review completed</span></div>${item.status !== 'pending' ? `<div class="admin-timeline-row"><strong>Status changed to ${item.status}</strong><span>Jul 30, 2026 · Admin User</span></div>` : ''}</div>
            <div class="admin-modal-actions"><button class="admin-button" data-modal-close>Close</button>${allowed.map(([action,label,kind]) => `<button class="admin-button ${kind}" data-request-action="${action}" data-request-id="${item.id}">${label}</button>`).join('')}</div>`);
    }

    function confirmRequestAction(id, action) {
        const item = fundingRequests.find(row => row.id === id);
        if (!item) return;
        const labels = { approve:'approve', reject:'reject', cancel:'cancel', complete:'mark as completed' };
        openModal(`<h2>Confirm action</h2><p>Are you sure you want to ${labels[action]} <strong>${escapeHtml(item.project)}</strong>?</p><div class="admin-field" style="margin-top:16px"><label>Decision note</label><input class="admin-input" style="width:100%" placeholder="Add a note (optional)" data-action-note></div><div class="admin-modal-actions"><button class="admin-button" data-modal-close>Keep unchanged</button><button class="admin-button ${action === 'reject' ? 'danger' : action === 'cancel' ? 'warning' : 'success'}" data-action-confirm="${action}" data-request-id="${id}">Confirm</button></div>`);
    }

    function securityDialog(type) {
        const config = {
            email: ['Edit email address', 'New email address', 'email', 'admin.new@giftofhope.org'],
            phone: ['Edit phone number', 'New phone number', 'tel', '+63 917 555 0100'],
            password: ['Change password', 'New password', 'password', 'Enter a strong password'],
        }[type];
        openModal(`<h2>${config[0]}</h2><p>This frontend-only flow simulates requesting and verifying an OTP.</p><div class="admin-field" style="margin-top:18px"><label>${config[1]}</label><input class="admin-input" style="width:100%" type="${config[2]}" placeholder="${config[3]}" data-security-value></div>${type === 'password' ? '<div class="admin-field" style="margin-top:12px"><label>Confirm new password</label><input class="admin-input" style="width:100%" type="password" placeholder="Repeat new password"></div>' : ''}<div class="admin-modal-actions"><button class="admin-button" data-modal-close>Cancel</button><button class="admin-button primary" data-otp-send="${type}">Send OTP</button></div>`);
    }

    function openNotifications() {
        openModal(`<h2>Notifications</h2><p>Three updates need your attention.</p><div class="admin-notification-list">
            <div class="admin-notification-item"><i>!</i><div><strong>Smart box SB-003 is nearly full</strong><span>Container level reached 96% at Manila North · 8 minutes ago</span></div></div>
            <div class="admin-notification-item"><i>₱</i><div><strong>New funding request received</strong><span>Reading Corners requested ₱44,000 · 34 minutes ago</span></div></div>
            <div class="admin-notification-item"><i>✓</i><div><strong>Collection TXN-8072 verified</strong><span>₱12,480 was added to available funds · 42 minutes ago</span></div></div>
        </div><div class="admin-modal-actions"><button class="admin-button" data-notifications-read>Mark all as read</button><button class="admin-button primary" data-modal-close>Done</button></div>`);
    }

    function otpDialog(type) {
        openModal(`<h2>Verify your identity</h2><p>Enter the six-digit code sent to your registered ${type === 'phone' ? 'phone number' : 'email address'}. Use <strong>123456</strong> for this UI demo.</p><div class="admin-otp">${Array.from({length:6},(_,index)=>`<input inputmode="numeric" maxlength="1" data-otp-digit="${index}">`).join('')}</div><div class="admin-modal-actions"><button class="admin-button" data-modal-close>Cancel</button><button class="admin-button primary" data-otp-verify="${type}">Verify code</button></div>`);
    }

    app.addEventListener('click', event => {
        const target = event.target.closest('button,a');
        if (!target) return;
        if (target.matches('[data-sidebar-toggle]')) app.classList.toggle('sidebar-open');
        if (target.dataset.adminGo) navigate(target.dataset.adminGo);
        if (target.dataset.adminNav) navigate(target.dataset.adminNav);
        if (target.dataset.snackbar) showSnack(target.dataset.snackbar);
        if (target.matches('[data-theme-toggle]')) setDark(!app.classList.contains('is-dark'));
        if (target.matches('[data-notification-open]')) openNotifications();
        if (target.matches('[data-notifications-read]')) { app.querySelector('[data-notification-open]').classList.remove('has-dot'); closeModal(); showSnack('All notifications marked as read.'); }
        if (target.matches('[data-modal-close]')) closeModal();
        if (target.dataset.statusTab) { state.status = target.dataset.statusTab; state.page = 1; app.querySelectorAll('[data-status-tab]').forEach(tab => tab.classList.toggle('is-active', tab === target)); renderFunding(); }
        if (target.dataset.fundingPage) { state.page = Number(target.dataset.fundingPage); renderFunding(); }
        if (target.matches('[data-page-prev]') && state.page > 1) { state.page--; renderFunding(); }
        if (target.matches('[data-page-next]') && state.page < Math.ceil(filteredFunding().length/state.pageSize)) { state.page++; renderFunding(); }
        if (target.dataset.requestView) openRequestDetails(target.dataset.requestView);
        if (target.dataset.requestAction) confirmRequestAction(target.dataset.requestId, target.dataset.requestAction);
        if (target.dataset.actionConfirm) {
            const item = fundingRequests.find(row => row.id === target.dataset.requestId);
            item.status = target.dataset.actionConfirm === 'complete' ? 'completed' : `${target.dataset.actionConfirm}d`.replace('canceld','cancelled');
            closeModal(); renderFunding(); showSnack(`${item.project} was ${item.status}. Mock data only.`);
        }
        if (target.dataset.reportTab) { app.querySelectorAll('[data-report-tab]').forEach(tab => tab.classList.toggle('is-active', tab === target)); app.querySelectorAll('[data-report-panel]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.reportPanel === target.dataset.reportTab)); }
        if (target.dataset.export) showSnack(`${target.dataset.export.toUpperCase()} export prepared. UI preview only.`);
        if (target.matches('[data-print]')) window.print();
        if (target.dataset.settingsTab) { app.querySelectorAll('[data-settings-tab]').forEach(tab => tab.classList.toggle('is-active', tab === target)); app.querySelectorAll('[data-settings-panel]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.settingsPanel === target.dataset.settingsTab)); }
        if (target.matches('[data-save-settings]')) showSnack('Settings saved for this browser session.');
        if (target.dataset.securityEdit) securityDialog(target.dataset.securityEdit);
        if (target.dataset.otpSend) otpDialog(target.dataset.otpSend);
        if (target.dataset.otpVerify) {
            const code = [...modalContent.querySelectorAll('[data-otp-digit]')].map(input => input.value).join('');
            if (code === '123456') { closeModal(); showSnack(`${target.dataset.otpVerify === 'password' ? 'Password' : target.dataset.otpVerify === 'email' ? 'Email' : 'Phone number'} verified and updated in the UI.`); }
            else { modalContent.querySelectorAll('[data-otp-digit]').forEach(input => { input.style.borderColor = '#dc2626'; input.value = ''; }); modalContent.querySelector('[data-otp-digit]')?.focus(); showSnack('Invalid OTP. Use 123456 for this UI demo.'); }
        }
        if (target.matches('[data-profile-upload]')) app.querySelector('[data-profile-input]').click();
        if (target.dataset.dashboardState) {
            const mode = target.dataset.dashboardState;
            app.querySelector('[data-dashboard-content]').style.display = mode === 'ready' ? '' : 'none';
            app.querySelector('[data-dashboard-loading]').classList.toggle('admin-state-visible', mode === 'loading');
            app.querySelector('[data-dashboard-error]').classList.toggle('admin-state-visible', mode === 'error');
            if (mode === 'loading') setTimeout(() => app.querySelector('[data-dashboard-state="ready"]')?.click(), 900);
        }
    });

    app.addEventListener('input', event => {
        if (event.target.matches('[data-funding-search]')) { state.query = event.target.value; state.page = 1; renderFunding(); }
        if (event.target.matches('[data-report-search]')) {
            const query = event.target.value.toLowerCase();
            app.querySelectorAll('.admin-report-panel.is-active tbody tr').forEach(row => row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none');
        }
        if (event.target.matches('[data-otp-digit]')) {
            event.target.value = event.target.value.replace(/\D/g,'');
            if (event.target.value) event.target.nextElementSibling?.focus();
        }
        if (event.target.matches('[data-global-search]') && event.target.value.length > 2) {
            const query = event.target.value.toLowerCase();
            const page = ['funding','request','project'].some(term => query.includes(term)) ? 'funding' : ['report','iot'].some(term => query.includes(term)) ? 'reports' : ['setting','profile','security'].some(term => query.includes(term)) ? 'settings' : null;
            if (page) navigate(page);
        }
    });

    app.addEventListener('change', event => {
        if (event.target.matches('[data-funding-category]')) { state.category = event.target.value; state.page = 1; renderFunding(); }
        if (event.target.matches('[data-funding-sort]')) { state.sort = event.target.value; state.page = 1; renderFunding(); }
        if (event.target.matches('[data-dark-switch]')) setDark(event.target.checked);
        if (event.target.matches('[data-compact-switch]')) { app.classList.toggle('is-compact', event.target.checked); localStorage.setItem('giftOfHopeAdminDensity', event.target.checked ? 'compact' : 'comfortable'); showSnack(`Table density changed to ${event.target.checked ? 'compact' : 'comfortable'}.`); }
        if (event.target.matches('[data-profile-input]') && event.target.files?.[0]) {
            const reader = new FileReader();
            reader.onload = () => { const photo = app.querySelector('.admin-profile-photo'); photo.style.background = `center/cover url(${reader.result})`; [...photo.childNodes].filter(node => node.nodeType === Node.TEXT_NODE).forEach(node => node.textContent = ''); showSnack('Profile picture preview updated.'); };
            reader.readAsDataURL(event.target.files[0]);
        }
    });

    app.querySelectorAll('[data-confirm-form]').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.confirmed === 'true') return;
        event.preventDefault();
        const button = event.submitter;
        openModal(`<h2>Confirm ${button.value}</h2><p>This uses the project’s existing approval workflow and will continue to OTP verification.</p><div class="admin-modal-actions"><button class="admin-button" data-modal-close>Cancel</button><button class="admin-button ${button.value === 'approved' ? 'success' : 'danger'}" data-live-confirm>${button.value === 'approved' ? 'Approve request' : 'Reject request'}</button></div>`);
        modalContent.querySelector('[data-live-confirm]').addEventListener('click', () => { form.dataset.confirmed = 'true'; form.requestSubmit(button); });
    }));

    function setDark(enabled) {
        app.classList.toggle('is-dark', enabled);
        app.querySelectorAll('[data-dark-switch]').forEach(toggle => toggle.checked = enabled);
        localStorage.setItem('giftOfHopeAdminTheme', enabled ? 'dark' : 'light');
    }

    document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeModal(); app.classList.remove('sidebar-open'); } });
    setDark(localStorage.getItem('giftOfHopeAdminTheme') === 'dark');
    const compactEnabled = localStorage.getItem('giftOfHopeAdminDensity') === 'compact';
    app.classList.toggle('is-compact', compactEnabled);
    app.querySelectorAll('[data-compact-switch]').forEach(toggle => toggle.checked = compactEnabled);
    const hasSpaPages = app.querySelectorAll('[data-admin-page]').length > 0;
    if (hasSpaPages) {
        window.addEventListener('hashchange', () => navigate(location.hash.slice(1)));
        navigate(location.hash.slice(1) || 'dashboard');
        renderFunding();
    }

    initAdminIotMonitor(app);
}

function initAdminIotMonitor(app) {
    const monitor = app.querySelector('[data-admin-iot-monitor]');
    if (!monitor) return;

    const onlineLabel = monitor.querySelector('[data-admin-iot-online-label]');
    const totalLabel = monitor.querySelector('[data-admin-iot-total]');
    const trackedBoxes = new Map();
    const missedChecksBeforeOffline = 4;
    let latestBoxes = null;

    const renderLiveCount = boxes => {
        const boxIds = Object.keys(boxes || {});
        let onlineCount = 0;

        boxIds.forEach(boxId => {
            const box = boxes[boxId] || {};
            const heartbeat = Number(box.heartbeat || 0);
            const previous = trackedBoxes.get(boxId);

            if (heartbeat === 0) {
                trackedBoxes.set(boxId, { heartbeat: 0, missedChecks: 0, online: false });
                return;
            }

            if (!previous) {
                const lastSeen = Number(box.lastSeen || 0);
                const recentlySeen = lastSeen > 0 && (Date.now() - lastSeen) <= 12000;
                trackedBoxes.set(boxId, {
                    heartbeat,
                    missedChecks: recentlySeen ? 0 : missedChecksBeforeOffline,
                    online: recentlySeen,
                });
                if (recentlySeen) onlineCount++;
                return;
            }

            const heartbeatChanged = heartbeat !== previous.heartbeat;
            const missedChecks = heartbeatChanged ? 0 : previous.missedChecks + 1;
            const online = heartbeatChanged || (previous.online && missedChecks < missedChecksBeforeOffline);
            trackedBoxes.set(boxId, { heartbeat, missedChecks, online });
            if (online) onlineCount++;
        });

        trackedBoxes.forEach((_, boxId) => {
            if (!Object.prototype.hasOwnProperty.call(boxes || {}, boxId)) trackedBoxes.delete(boxId);
        });

        onlineLabel.textContent = `${onlineCount} online`;
        totalLabel.textContent = boxIds.length;
    };

    const refresh = () => renderLiveCount(latestBoxes);

    fetch('/config/firebase')
        .then(response => {
            if (!response.ok) throw new Error(`Firebase configuration request failed (${response.status})`);
            return response.json();
        })
        .then(firebaseConfig => {
            const database = getDatabase(initializeApp(firebaseConfig, 'admin-iot-monitor'));
            onValue(ref(database, '/boxes'), snapshot => {
                latestBoxes = snapshot.val();
                refresh();
            }, error => {
                console.error('Admin Firebase read failed:', error);
                onlineLabel.textContent = 'Live status unavailable';
            });
        })
        .catch(error => {
            console.error('Admin Firebase monitor initialization failed:', error);
            onlineLabel.textContent = 'Live status unavailable';
        });

    setInterval(refresh, 3000);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAdminUI);
else initAdminUI();
