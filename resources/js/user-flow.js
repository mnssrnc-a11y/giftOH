const shell = document.querySelector('.hope-shell');
if (shell) {
    const toast = (message) => {
        const element = shell.querySelector('.hope-toast');
        element.textContent = message;
        element.hidden = false;
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(() => { element.hidden = true; }, 5500);
    };
    const open = (id) => document.getElementById(id)?.showModal();
    const close = (element) => element.closest('dialog')?.close();
    shell.querySelectorAll('[data-open-dialog]').forEach(button => button.addEventListener('click', () => open(button.dataset.openDialog)));
    shell.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => close(button)));
    shell.querySelectorAll('[data-switch-dialog]').forEach(button => button.addEventListener('click', () => { close(button); open(button.dataset.switchDialog); }));
    const menu = shell.querySelector('.hope-menu');
    menu?.addEventListener('click', () => {
        const expanded = shell.querySelector('.hope-sidebar').classList.toggle('is-open');
        menu.setAttribute('aria-expanded', String(expanded));
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') { shell.querySelector('.hope-sidebar').classList.remove('is-open'); menu?.setAttribute('aria-expanded', 'false'); }
    });
    // Dark mode is an account preference saved on the server (see Settings ->
    // Preferences). The server renders both classes below on `.hope-shell`
    // before this script runs (dashboard.blade.php), so there is no flash of
    // the wrong theme and no dependency on localStorage for the source of
    // truth:
    //   - "hope-dark" drives the hope-* component system (sidebar, topbar,
    //     hope-card, etc. via CSS variables scoped to .hope-dark).
    //   - "dark" is Tailwind's own convention, used by every page built with
    //     plain utility classes (bg-white, bg-gray-50, text-gray-900, ...);
    //     see the ".dark ..." rules in app.css and the dark: variant in
    //     theme.css. Both classes must be kept in sync or only the nav
    //     (hope-* markup) goes dark while the page content (Tailwind markup)
    //     stays light.
    const applyDarkMode = (isDark) => {
        shell.classList.toggle('hope-dark', isDark);
        shell.classList.toggle('dark', isDark);
    };
    applyDarkMode(shell.dataset.darkMode === 'true');
    const darkToggle = shell.querySelector('[data-dark-preference]');
    if (darkToggle) {
        // The checkbox's checked state is already rendered from the account
        // value by settings.blade.php. Toggling it here only previews the
        // change instantly; it is saved to the account when the Preferences
        // form is submitted.
        darkToggle.addEventListener('change', () => {
            applyDarkMode(darkToggle.checked);
            toast(`Dark mode ${darkToggle.checked ? 'on' : 'off'} — click "Save preferences" to keep this on your account.`);
        });
    }
    shell.querySelector('[data-email-preference]')?.addEventListener('change', event => toast(`Email notifications ${event.target.checked ? 'on' : 'off'} — click "Save preferences" to keep this on your account.`));
    const filterItems = [...shell.querySelectorAll('[data-filter-item]')];
    const search = shell.querySelector('[data-filter-search]');
    const select = shell.querySelector('[data-filter-select]');
    const filter = () => {
        const query = search?.value.trim().toLowerCase() || '';
        filterItems.forEach(item => { item.hidden = !item.textContent.toLowerCase().includes(query) || Boolean(select?.value && item.dataset.category !== select.value); });
        const empty = shell.querySelector('[data-filter-empty]');
        if (empty) empty.hidden = filterItems.some(item => !item.hidden);
    };
    search?.addEventListener('input', filter);
    select?.addEventListener('change', filter);
    shell.querySelectorAll('[data-join]').forEach(button => button.addEventListener('click', () => {
        shell.querySelectorAll('[data-join]').forEach(other => {
            if (other.dataset.join === button.dataset.join) { other.textContent = 'Request pending (preview)'; other.disabled = true; }
        });
        toast('Join request previewed. No request was sent to the group.');
    }));
    shell.querySelector('[data-mark-read]')?.addEventListener('click', event => {
        shell.querySelectorAll('[data-unread]').forEach(badge => { badge.hidden = true; });
        event.target.textContent = 'All read'; event.target.disabled = true;
        toast('Example notifications marked as read for this visit.');
    });
    const validateFile = (input) => {
        const file = input.files[0];
        const extensions = input.accept.split(',').map(value => value.trim().toLowerCase());
        const extension = file ? `.${file.name.split('.').pop().toLowerCase()}` : '';
        let error = '';
        if (file && !extensions.includes(extension)) error = `Choose a ${extensions.join(', ')} file.`;
        else if (file && file.size > Number(input.dataset.maxMb) * 1024 * 1024) error = `File must be ${input.dataset.maxMb} MB or smaller.`;
        input.setCustomValidity(error);
        return error;
    };
    shell.querySelectorAll('[data-validate-file]').forEach(input => input.addEventListener('change', () => {
        const error = validateFile(input);
        const output = input.closest('form')?.querySelector('[data-form-error]') || shell.querySelector('#photo-error');
        if (output) output.textContent = error;
    }));
    let photoUrl;
    shell.querySelector('[data-profile-photo]')?.addEventListener('change', event => {
        const input = event.target;
        const image = shell.querySelector('#profile-photo-preview');
        const initial = shell.querySelector('#profile-initial');
        if (photoUrl) URL.revokeObjectURL(photoUrl);
        image.hidden = true; initial.hidden = false;
        if (validateFile(input) || !input.files[0]) return;
        photoUrl = URL.createObjectURL(input.files[0]);
        image.onload = () => { image.hidden = false; initial.hidden = true; toast('Profile photo preview updated. Your account photo is unchanged.'); };
        image.onerror = () => { shell.querySelector('#photo-error').textContent = 'This image could not be opened. Choose a valid JPG or PNG.'; };
        image.src = photoUrl;
    });
    shell.querySelectorAll('[data-preview-form]').forEach(form => form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        close(form); toast(form.dataset.previewForm);
    }));
    shell.querySelector('[data-profile-form]')?.addEventListener('submit', event => {
        event.preventDefault();
        const values = shell.querySelector('#profile-review-values');
        values.replaceChildren();
        event.target.querySelectorAll('input').forEach(input => {
            const group = document.createElement('div');
            const label = document.createElement('dt');
            const value = document.createElement('dd');
            label.textContent = input.labels[0].textContent; value.textContent = input.value || 'Not provided';
            group.append(label, value); values.append(group);
        });
        open('profile-review');
    });
    let verificationTarget;
    shell.querySelectorAll('[data-verification-target]').forEach(button => button.addEventListener('click', () => {
        verificationTarget = button.dataset.verificationTarget;
        shell.querySelector('[data-verification-form]').reset();
        shell.querySelector('[data-code-error]').textContent = '';
        close(button); open('verification-preview');
    }));
    shell.querySelector('[data-preview-resend]')?.addEventListener('click', () => {
        shell.querySelector('[data-code-error]').textContent = 'Preview code: 123456. No email was sent.';
    });
    shell.querySelector('[data-verification-form]')?.addEventListener('submit', event => {
        event.preventDefault();
        if (shell.querySelector('#preview-code').value !== '123456') { shell.querySelector('[data-code-error]').textContent = 'Invalid preview code. Enter 123456 to continue.'; return; }
        close(event.target);
        if (verificationTarget === 'password') { shell.querySelector('[data-password-preview]').reset(); shell.querySelector('[data-password-error]').textContent = ''; open('password-preview'); }
        else toast('Profile update preview completed. Your account information is unchanged.');
    });
    shell.querySelector('[data-password-preview]')?.addEventListener('submit', event => {
        event.preventDefault();
        if (shell.querySelector('#new-password').value !== shell.querySelector('#confirm-password').value) { shell.querySelector('[data-password-error]').textContent = 'The passwords do not match.'; return; }
        event.target.reset(); close(event.target); toast('Password update preview completed. Your actual password is unchanged.');
    });
}