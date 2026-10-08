// Requester workspace: dialogs, the mobile menu, dark mode, list filters and file checks.
const shell = document.querySelector('.hope-shell');
if (shell) {
    const open = (id) => document.getElementById(id)?.showModal();
    const close = (element) => element.closest('dialog')?.close();
    shell.querySelectorAll('[data-open-dialog]').forEach(button => button.addEventListener('click', () => open(button.dataset.openDialog)));
    shell.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => close(button)));
    shell.querySelectorAll('[data-switch-dialog]').forEach(button => button.addEventListener('click', () => { close(button); open(button.dataset.switchDialog); }));
    const menu = shell.querySelector('.hope-menu');
    menu?.addEventListener('click', () => {
        const expanded = shell.querySelector('.ws-sidebar').classList.toggle('is-open');
        menu.setAttribute('aria-expanded', String(expanded));
    });
    const closeMenu = () => { shell.querySelector('.ws-sidebar').classList.remove('is-open'); menu?.setAttribute('aria-expanded', 'false'); };
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
    document.addEventListener('click', event => {
        if (!event.target.closest('.ws-sidebar, .ws-menu')) closeMenu();
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
}