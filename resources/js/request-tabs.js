// Admin request page: the procedure one step at a time (interview → documents → assessment →
// decision → release → liquidation), and the request's details in tabs, so only what the current
// step needs is on screen. The server opens the step the request is at; links like #documents or
// #notes (after an action) open the step or tab that holds them.
const groups = [...document.querySelectorAll('[data-tabs]')].map(root => {
    const tabs = [...root.querySelectorAll('[data-tab]')].filter(tab => tab.closest('[data-tabs]') === root);
    const panels = [...root.querySelectorAll('[data-tab-panel]')].filter(panel => panel.closest('[data-tabs]') === root);

    function show(name, { focus = false } = {}) {
        const tab = tabs.find(t => t.dataset.tab === name);
        if (!tab || tab.getAttribute('aria-disabled') === 'true') return false;
        tabs.forEach(t => {
            const on = t === tab;
            t.setAttribute('aria-selected', String(on));
            t.tabIndex = on ? 0 : -1;
        });
        panels.forEach(panel => { panel.hidden = panel.dataset.tabPanel !== name; });
        if (focus) tab.focus();
        return true;
    }

    tabs.forEach(tab => {
        tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;
        tab.addEventListener('click', () => {
            if (show(tab.dataset.tab)) history.replaceState(history.state, '', `#${tab.dataset.tab}`);
        });
        // Arrow keys move between the tabs that are open.
        tab.addEventListener('keydown', event => {
            const open = tabs.filter(t => t.getAttribute('aria-disabled') !== 'true');
            const index = open.indexOf(tab);
            const next = { ArrowRight: open[index + 1], ArrowLeft: open[index - 1], Home: open[0], End: open.at(-1) }[event.key];
            if (next) { event.preventDefault(); show(next.dataset.tab, { focus: true }); }
        });
    });
    // "Next: documents →" buttons inside a step.
    root.addEventListener('click', event => {
        const go = event.target.closest('[data-tab-go]');
        if (go && show(go.dataset.tabGo)) {
            history.replaceState(history.state, '', `#${go.dataset.tabGo}`);
            root.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    });

    return { root, show, panels };
});

// Open the step or tab that holds #something.
function openHash() {
    const id = decodeURIComponent(location.hash.slice(1));
    const target = id && document.getElementById(id);
    if (!target) return;
    for (const group of groups) {
        const panel = target.closest('[data-tab-panel]');
        if (panel && group.panels.includes(panel)) {
            group.show(panel.dataset.tabPanel);
            requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));
        }
    }
}

if (groups.length) {
    openHash();
    addEventListener('hashchange', openHash);
}
