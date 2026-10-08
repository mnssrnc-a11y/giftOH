// Notification bell in every workspace topbar: opens the latest notifications, closes on an
// outside click or Escape. Opening a notification is a normal form post (it follows its link).
document.querySelectorAll('[data-notify]').forEach(bell => {
    const button = bell.querySelector('[data-notify-toggle]');
    const panel = bell.querySelector('[data-notify-panel]');
    if (!button || !panel) return;

    const setOpen = open => {
        panel.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        if (open) panel.querySelector('button, a')?.focus();
    };

    button.addEventListener('click', event => {
        event.stopPropagation();
        setOpen(panel.hidden);
    });
    document.addEventListener('click', event => {
        if (!panel.hidden && !bell.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            button.focus();
        }
    });
});
