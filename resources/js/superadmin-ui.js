const root = document.querySelector('[data-superadmin]');
if (root) {
    const $ = (s, scope = root) => scope.querySelector(s);
    const $$ = (s) => [...root.querySelectorAll(s)];
    const escape = (value) => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money = value => new Intl.NumberFormat('en-PH', {style:'currency', currency:'PHP', maximumFractionDigits:0}).format(value);
    const accounts = [
        {id:1,name:'Maria Santos',email:'maria@example.test',role:'Admin',status:'Enabled',activity:'Today, 09:42'},
        {id:2,name:'Daniel Reyes',email:'daniel@example.test',role:'Admin',status:'Enabled',activity:'Today, 09:15'},
        {id:3,name:'Ana Cruz',email:'ana@example.test',role:'User',status:'Enabled',activity:'Today, 08:30'},
        {id:4,name:'Miguel Ramos',email:'miguel@example.test',role:'User',status:'Enabled',activity:'Yesterday, 16:20'},
        {id:5,name:'Sofia Garcia',email:'sofia@example.test',role:'User',status:'Disabled',activity:'Sep 12, 14:05'},
        {id:6,name:'Luis Mendoza',email:'luis@example.test',role:'User',status:'Enabled',activity:'Sep 14, 10:12'},
    ];
    const requests = [
        {id:'FR-001',name:'Maria Santos',title:'Back-to-school essentials',category:'Education',amount:15000,status:'Pending',date:'2026-09-15',reason:'School supplies and learning materials for 30 community students.'},
        {id:'FR-002',name:'Daniel Reyes',title:'Community health assistance',category:'Medical',amount:20000,status:'Pending',date:'2026-09-14',reason:'Medical consultations and essential medicines for families in need.'},
        {id:'FR-003',name:'Maria Santos',title:'A meal for every family',category:'Food',amount:8000,status:'Pending',date:'2026-09-14',reason:'Food packages for 40 households through our community pantry.'},
        {id:'FR-004',name:'Daniel Reyes',title:'Emergency home repairs',category:'Shelter',amount:25000,status:'Approved',date:'2026-09-10',reason:'Repair materials for storm-damaged homes.'},
        {id:'FR-005',name:'Maria Santos',title:'Learning center equipment',category:'Education',amount:12000,status:'Denied',date:'2026-09-08',reason:'Replacement equipment for a community learning center.'},
    ];
    const devices = [['BOX-001','Community center, Quezon City','Online','Today, 09:42'],['BOX-002','Parish hall, Manila','Online','Today, 09:41'],['BOX-003','Public market, Pasig','Online','Today, 09:40'],['BOX-004','Barangay hall, Makati','Offline','Yesterday, 18:20']];
    let scores = {Education:85,Food:75,Medical:95,Shelter:80};
    const logs = [{action:'Fund request FR-001 submitted',actor:'Maria Santos',time:'Sep 15, 2026, 09:42',source:'Sample'}, {action:'Donation box BOX-004 went offline',actor:'System',time:'Sep 14, 2026, 18:20',source:'Sample'}, {action:'Fund request FR-004 approved',actor:'Superadmin',time:'Sep 10, 2026, 14:05',source:'Sample'}];
    const dialog = $('[data-sa-dialog]');
    const content = $('[data-sa-dialog-content]');
    let toastTimer;
    const toast = text => { const el = $('.sa-toast'); el.textContent=text; el.hidden=false; clearTimeout(toastTimer); toastTimer=setTimeout(()=>el.hidden=true,4500); };
    const badge = status => `<span class="sa-badge ${['Pending'].includes(status)?'amber':['Denied','Disabled','Offline'].includes(status)?'red':''}">${escape(status)}</span>`;
    const empty = columns => `<tr><td colspan="${columns}" class="sa-empty">No matches found. Try another search or filter.</td></tr>`;
    const setText = (selector,value) => $$(selector).forEach(el=>el.textContent=value);
    const open = html => { content.innerHTML=html; if(!dialog.open) dialog.showModal(); else content.querySelector('input,button,select')?.focus(); };
    const actions = (text='Confirm preview change') => `<div class="sa-actions"><button type="button" class="sa-button secondary" data-sa-close>Cancel</button><button class="sa-button" type="submit">${text}</button></div>`;
    const log = action => { logs.unshift({action,actor:'You (preview)',time:new Date().toLocaleString(),source:'Preview'}); render(); };
    function confirmChange(title, body, callback) {
        open(`<h2 id="sa-dialog-title">${escape(title)}</h2><p>${body}</p><form data-confirm>${actions()}</form>`);
        $('[data-confirm]',content).addEventListener('submit',event=>{event.preventDefault();callback();dialog.close();});
    }
    function render() {
        const pending=requests.filter(r=>r.status==='Pending');
        const approved=requests.filter(r=>r.status==='Approved');
        const denied=requests.filter(r=>r.status==='Denied');
        const rate=Math.round(approved.length/(approved.length+denied.length)*100)||0;
        setText('[data-sa-pending]',pending.length); setText('[data-sa-rate]',`${rate}%`);
        setText('[data-sa-account-total]',accounts.length); setText('[data-sa-admin-total]',`${accounts.filter(a=>a.role==='Admin').length} administrators`);
        setText('[data-sa-approved]',approved.length); setText('[data-sa-denied]',denied.length);
        setText('[data-sa-approved-amount]',money(approved.reduce((sum,r)=>sum+r.amount,0)));
        $('[data-sa-outcome-progress]').value=rate;
        $('[data-sa-scores]').innerHTML=Object.entries(scores).map(([name,score])=>`<div class="sa-score"><div><span>${escape(name)}</span><strong>${score} / 100</strong></div><progress value="${score}" max="100" aria-label="${escape(name)} score"></progress></div>`).join('');
        const query=$('[data-sa-account-search]').value.toLowerCase();
        const role=$('[data-sa-role-filter]').value; const status=$('[data-sa-status-filter]').value;
        $('[data-sa-accounts]').innerHTML=accounts.filter(a=>(a.name+' '+a.email).toLowerCase().includes(query)&&(!role||role===a.role)&&(!status||status===a.status)).map(a=>`<tr><td><strong>${escape(a.name)}</strong><small>${escape(a.email)}</small></td><td>${escape(a.role)}</td><td>${badge(a.status)}</td><td>${escape(a.activity)}</td><td><button class="sa-link" data-sa-account="${a.id}">Manage ↗</button></td></tr>`).join('')||empty(5);
        $('[data-sa-request-preview]').innerHTML=pending.slice(0,3).map(r=>`<tr><td><strong>${escape(r.name)}</strong><small>${escape(r.title)}</small></td><td>${money(r.amount)}</td><td><button class="sa-link" data-sa-request="${r.id}">Review ↗</button></td></tr>`).join('')||'<tr><td colspan="3" class="sa-empty">All requests have been reviewed.</td></tr>';
        const rq=$('[data-sa-request-search]').value.toLowerCase(); const rs=$('[data-sa-request-filter]').value;
        $('[data-sa-requests]').innerHTML=requests.filter(r=>(r.name+' '+r.title+' '+r.id+' '+r.category).toLowerCase().includes(rq)&&(!rs||rs===r.status)).map(r=>`<tr><td><strong>${escape(r.title)}</strong><small>${r.id} · ${escape(r.name)}</small></td><td>${r.category}</td><td>${money(r.amount)}</td><td>${badge(r.status)}</td><td><button class="sa-link" data-sa-request="${r.id}">${r.status==='Pending'?'Review':'View'} ↗</button></td></tr>`).join('')||empty(5);
        $$('[data-sa-devices]').forEach(el=>el.innerHTML=devices.map(([id,location,status,seen])=>`<div class="sa-device"><div><strong>${id}</strong><small>${location}</small><small>Last seen: ${seen} · sample</small></div>${badge(status)}</div>`).join(''));
        const events=logs.slice(0,4).map(l=>`<div class="sa-event"><span class="sa-event-icon" aria-hidden="true">↗</span><div><strong>${escape(l.action)}</strong><small>${escape(l.actor)} · ${escape(l.time)}</small></div></div>`).join('');
        $('[data-sa-recent]').innerHTML=events; $('[data-sa-audit]').innerHTML=events;
        const lq=$('[data-sa-log-search]').value.toLowerCase();
        $('[data-sa-logs]').innerHTML=logs.filter(l=>(l.action+' '+l.actor).toLowerCase().includes(lq)).map(l=>`<tr><td>${escape(l.action)}</td><td>${escape(l.actor)}</td><td>${escape(l.time)}</td><td>${badge(l.source)}</td></tr>`).join('')||empty(4);
    }
    function navigate() {
        let page=location.hash.slice(1)||'dashboard';
        if(!$$('[data-sa-page]').some(el=>el.dataset.saPage===page)) page='dashboard';
        $$('[data-sa-page]').forEach(el=>el.hidden=el.dataset.saPage!==page);
        $$('[data-sa-nav]').forEach(el=>{if(el.dataset.saNav===page){el.setAttribute('aria-current','page');$('[data-sa-title]').textContent=el.childNodes[1].textContent;}else el.removeAttribute('aria-current');});
        root.classList.remove('nav-open'); $('.sa-menu').setAttribute('aria-expanded','false');
    }
    root.addEventListener('input',e=>{if(e.target.matches('[type=search]')) render();});
    root.addEventListener('change',e=>{if(e.target.matches('.sa-toolbar select')) render();});
    window.addEventListener('hashchange',navigate);
    root.addEventListener('click', e=> {
        if(e.target.closest('[data-sa-close]')) dialog.close();
        if(e.target.closest('.sa-menu')) {const active=root.classList.toggle('nav-open');$('.sa-menu').setAttribute('aria-expanded',String(active));}
        if(e.target.closest('[data-sa-create]')) createAdmin();
        const account=e.target.closest('[data-sa-account]'); if(account) manageAccount(accounts.find(a=>a.id===Number(account.dataset.saAccount)));
        const request=e.target.closest('[data-sa-request]'); if(request) reviewRequest(requests.find(r=>r.id===request.dataset.saRequest));
    });
    function manageAccount(account) {
        open(`<h2 id="sa-dialog-title">Manage account</h2><p>Review activity and update preview permissions.</p><dl><dt>Name</dt><dd>${escape(account.name)}</dd><dt>Email</dt><dd>${escape(account.email)}</dd><dt>Last activity</dt><dd>${escape(account.activity)}</dd><dt>Current access</dt><dd>${badge(account.status)}</dd></dl><form data-account-form><div class="sa-fields"><label class="sa-field">Account role<select name="role"><option ${account.role==='User'?'selected':''}>User</option><option ${account.role==='Admin'?'selected':''}>Admin</option></select></label><label class="sa-field">Account access<select name="status"><option ${account.status==='Enabled'?'selected':''}>Enabled</option><option ${account.status==='Disabled'?'selected':''}>Disabled</option></select></label></div><label class="sa-check"><input type="checkbox" required> I agree to change this account's preview permissions.</label>${actions('Review permissions')}</form>`);
        $('[data-account-form]',content).addEventListener('submit',e=>{e.preventDefault();const data=new FormData(e.target);const role=data.get('role'),status=data.get('status');confirmChange('Confirm account permissions',`${escape(account.name)} will have the ${escape(role)} role with ${escape(status.toLowerCase())} access.`,()=>{account.role=role;account.status=status;account.activity='Just now (preview)';log(`Permissions updated for ${account.name}: ${role}, ${status}`);toast('Preview account permissions updated.');});});
    }
    function createAdmin() {
        open(`<h2 id="sa-dialog-title">Create administrator</h2><p>Enter sample details to walk through account creation and email verification.</p><form data-create-form><div class="sa-fields"><label class="sa-field">First name<input name="first" maxlength="60" required></label><label class="sa-field">Middle initial (optional)<input name="middle" maxlength="3"></label><label class="sa-field">Last name<input name="last" maxlength="60" required></label><label class="sa-field">Contact number<input type="tel" name="phone" pattern="[+0-9 ()-]{7,20}" required></label><label class="sa-field full">Email<input type="email" name="email" required></label><label class="sa-field">Password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label><label class="sa-field">Confirm password<input type="password" name="confirm" minlength="8" autocomplete="new-password" required></label></div><div class="sa-error" role="alert" data-create-error></div>${actions('Continue to verification')}</form>`);
        $('[data-create-form]',content).addEventListener('submit',e=>{
            e.preventDefault(); const data=new FormData(e.target);const error=$('[data-create-error]',content);
            if(data.get('password')!==data.get('confirm')){error.textContent='Passwords must match.';return;}
            const email=data.get('email').trim(); if(accounts.some(a=>a.email.toLowerCase()===email.toLowerCase())){error.textContent='An account with this email already exists.';return;}
            if(!data.get('first').trim()||!data.get('last').trim()){error.textContent='First and last names cannot be blank.';return;}
            const draft={id:Math.max(...accounts.map(a=>a.id))+1,name:[data.get('first'),data.get('middle'),data.get('last')].map(s=>s.trim()).filter(Boolean).join(' '),email,role:'Admin',status:'Enabled',activity:'Created just now'};
            e.target.reset(); verifyAdmin(draft);
        });
    }
    function verifyAdmin(draft) {
        let expires=Date.now()+300000;
        open(`<h2 id="sa-dialog-title">Verify administrator email</h2><p>Preview verification for ${escape(draft.email)}. No email has been sent.</p><div class="sa-note">Demo code: <strong>123456</strong> · expires in 5 minutes.</div><form data-verify style="margin-top:20px"><label class="sa-field">Verification code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><div class="sa-error" role="alert" data-code-error></div><button type="button" class="sa-link" data-resend style="margin-top:12px">Generate demo code again</button>${actions('Create preview admin')}</form>`);
        $('[data-resend]',content).addEventListener('click',()=>{expires=Date.now()+300000;$('[data-code-error]',content).textContent='Demo code refreshed: 123456. No email sent.';});
        $('[data-verify]',content).addEventListener('submit',e=>{e.preventDefault();if(Date.now()>expires||new FormData(e.target).get('code')!=='123456'){$('[data-code-error]',content).textContent='Code is incorrect or expired. Generate a new demo code and try again.';return;}accounts.push(draft);log(`Administrator created: ${draft.name}`);dialog.close();location.hash='accounts';toast('Preview administrator created. No real account was added.');});
    }
    function reviewRequest(request) {
        open(`<h2 id="sa-dialog-title">${escape(request.title)}</h2><p>${request.id} · Submitted ${request.date}</p><dl><dt>Administrator</dt><dd>${escape(request.name)}</dd><dt>Category</dt><dd>${request.category}</dd><dt>Requested amount</dt><dd>${money(request.amount)}</dd><dt>Status</dt><dd>${badge(request.status)}</dd><dt>Purpose</dt><dd>${escape(request.reason)}</dd></dl>${request.status==='Pending'?'<div class="sa-actions"><button class="sa-button secondary" data-decision="Denied">Deny request</button><button class="sa-button" data-decision="Approved">Approve request</button></div>':'<div class="sa-note">This sample request has already been reviewed.</div>'}`);
        content.querySelectorAll('[data-decision]').forEach(button=>button.addEventListener('click',()=>{
            const status=button.dataset.decision;
            open(`<h2 id="sa-dialog-title">${status==='Approved'?'Approve':'Deny'} ${request.id}?</h2><p>${escape(request.title)} · ${money(request.amount)}<br>This records a preview decision. No funds move and no notice is emailed.</p><form data-decision-form>${status==='Approved'?'<div class="sa-note">Agreement: I have reviewed the request details and agree to approve this funding request.</div><label class="sa-field" style="margin-top:18px">Type Agree to confirm<input name="agreement" required pattern="Agree" placeholder="Agree" autocomplete="off"></label>':'<label class="sa-check"><input type="checkbox" required> I confirm this request should be denied.</label>'}${actions(status==='Approved'?'Confirm approval':'Confirm denial')}</form>`);
            $('[data-decision-form]',content).addEventListener('submit',e=>{e.preventDefault();request.status=status;log(`${request.id} ${status.toLowerCase()} — notice simulated, not sent`);dialog.close();toast(`Request ${status.toLowerCase()} in preview. Email notice simulated.`);});
        }));
    }
    $$('[data-sa-settings]').forEach(form=>form.addEventListener('submit',e=>{
        e.preventDefault(); const data=new FormData(form); const type=form.dataset.saSettings;
        if(type==='scores') {
            const next=Object.fromEntries([...data].map(([key,value])=>[key,Number(value)]));
            confirmChange('Update AI scoring mechanics?',Object.entries(next).map(([k,v])=>`${escape(k)}: ${v}`).join(' · '),()=>{scores=next;log('AI scoring mechanics updated');toast('Preview scoring updated.');});
        } else if(type==='limit') {
            const days=Number(data.get('days'));
            if(!Number.isInteger(days)||days<=92)return;
            confirmChange('Update request interval?',`Change the interval from ${escape($('[data-sa-limit]').textContent)} to ${days} days?`,()=>{setText('[data-sa-limit]',`${days} days`);log(`Global request interval updated to ${days} days`);toast('Preview request interval updated.');});
        } else {
            const email=data.get('email').trim(), name=data.get('appname').trim();
            if(!name)return;
            // Discard the password immediately; it is never copied into state or logs.
            form.elements.password.value='';
            confirmChange('Update email settings?',`Use ${escape(email)} with app name ${escape(name)}? The preview does not connect to a mail server.`,()=>{setText('[data-sa-email]',email);setText('[data-sa-appname]',name);form.reset();log('Verification email configuration updated (preview)');toast('Preview email settings updated. No connection was made.');});
        }
    }));
    render(); navigate();
}
