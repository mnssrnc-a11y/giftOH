// Preference switches save the moment they change. Email verification asks for confirmation first,
// because it changes how the person signs in. Dark mode applies to the page right away.
const switches = [...document.querySelectorAll('[data-preference]')];

if (switches.length) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const status = document.querySelector('[data-preference-status]');
    const dialog = document.querySelector('[data-preference-dialog]');

    const say = (message, kind = 'ok') => {
        if (!status) return;
        status.textContent = message;
        status.dataset.kind = kind;
    };

    const applyDarkMode = enabled => {
        document.querySelector('.hope-shell')?.classList.toggle('hope-dark', enabled);
        document.querySelector('.hope-shell')?.classList.toggle('dark', enabled);
    };

    async function save(input, value) {
        const field = input.dataset.preference;
        input.disabled = true;
        say('Saving…', 'busy');
        try {
            const response = await fetch(input.form?.action ?? '/settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ [field]: value ? 1 : 0 }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.saved) throw new Error(result.message || 'Your preference could not be saved. Try again.');
            input.checked = value;
            if (field === 'dark_mode') applyDarkMode(value);
            say(result.message || 'Saved.');
        } catch (error) {
            input.checked = !value;
            say(error.message, 'error');
        } finally {
            input.disabled = false;
        }
    }

    function confirmEmail(input, enabling) {
        if (!dialog?.showModal) return save(input, enabling);
        dialog.querySelector('[data-preference-title]').textContent = enabling ? 'Turn on email verification?' : 'Turn off email verification?';
        dialog.querySelector('[data-preference-text]').textContent = enabling
            ? `From your next sign-in, a 6-digit code will be emailed to ${dialog.dataset.email}. Make sure you can open that inbox.`
            : 'You will sign in with your password only, without an emailed code. Your account will be less protected.';
        const confirm = dialog.querySelector('[data-preference-confirm]');
        confirm.textContent = enabling ? 'Turn on' : 'Turn off';
        confirm.onclick = () => { dialog.close(); save(input, enabling); };
        dialog.showModal();
    }

    dialog?.querySelector('[data-preference-cancel]')?.addEventListener('click', () => dialog.close());

    switches.forEach(input => input.addEventListener('change', () => {
        const value = input.checked;
        if (input.dataset.preference === 'email_notifications') {
            input.checked = !value; // nothing changes until the person confirms
            confirmEmail(input, value);
        } else {
            save(input, value);
        }
    }));

    // The form is only a fallback for browsers without JavaScript.
    switches[0].form?.addEventListener('submit', event => event.preventDefault());
}
