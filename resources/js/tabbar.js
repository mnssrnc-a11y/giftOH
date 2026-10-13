// Phone tab bar: "Menu" opens the workspace's sidebar, and in the admin and super admin
// workspaces (one page, several sections) the tab of the section on screen is marked current.
const bar = document.querySelector('[data-tabbar]');

if (bar) {
    bar.querySelector('[data-tab-menu]')?.addEventListener('click', event => {
        event.stopPropagation();
        document.querySelector('.ws-menu')?.click();
    });

    const sectionTabs = [...bar.querySelectorAll('[data-tab-section]')];
    const mark = section => sectionTabs.forEach(tab => tab.toggleAttribute('aria-current', tab.dataset.tabSection === section));
    if (sectionTabs.length && document.querySelector('[data-admin-page], [data-sa-page]')) {
        window.addEventListener('ws:section', event => mark(event.detail));
        mark((location.hash.slice(1) || 'dashboard').split('-')[0]);
    }
}
