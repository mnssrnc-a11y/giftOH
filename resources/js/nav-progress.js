// Instant feedback while the next page loads: a thin bar at the top starts the moment a link or
// form is used, so navigation never feels stuck while the server works.
const bar = document.querySelector('[data-nav-progress]');

if (bar) {
    let timer = null;
    const start = () => {
        clearTimeout(timer);
        bar.classList.remove('is-done');
        bar.classList.add('is-loading');
    };
    const stop = () => {
        bar.classList.add('is-done');
        timer = setTimeout(() => bar.classList.remove('is-loading', 'is-done'), 300);
    };

    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download')) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin) return;
        // Same page, different section (#hash): handled in the page, no load.
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
        start();
    });

    document.addEventListener('submit', event => {
        if (!event.defaultPrevented && !event.target.hasAttribute('data-no-progress')) start();
    });

    // Back/forward cache and prerendered pages arrive already loaded.
    addEventListener('pageshow', stop);
    addEventListener('pagehide', () => setTimeout(stop, 0));
}
