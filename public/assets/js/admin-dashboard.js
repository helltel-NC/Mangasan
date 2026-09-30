(() => {
    'use strict';

    const tabs = Array.from(document.querySelectorAll('[data-admin-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-admin-panel]'));

    if (tabs.length === 0 || panels.length === 0) {
        return;
    }

    const availableTabs = new Set(tabs.map((tab) => tab.dataset.adminTab));

    const activateTab = (tabName, updateHash = true) => {
        if (!availableTabs.has(tabName)) {
            tabName = 'overview';
        }

        tabs.forEach((tab) => {
            const active = tab.dataset.adminTab === tabName;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });

        panels.forEach((panel) => {
            const active = panel.dataset.adminPanel === tabName;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });

        if (updateHash) {
            const hash = tabName === 'overview' ? '#overview' : '#configuration';
            history.replaceState(null, '', hash);
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => {
            activateTab(tab.dataset.adminTab || 'overview');
        });

        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }

            event.preventDefault();
            let nextIndex = index;

            if (event.key === 'ArrowRight') {
                nextIndex = (index + 1) % tabs.length;
            } else if (event.key === 'ArrowLeft') {
                nextIndex = (index - 1 + tabs.length) % tabs.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = tabs.length - 1;
            }

            tabs[nextIndex].focus();
            activateTab(tabs[nextIndex].dataset.adminTab || 'overview');
        });
    });

    const initialTab = window.location.hash === '#configuration' ? 'configuration' : 'overview';
    activateTab(initialTab, false);
})();
