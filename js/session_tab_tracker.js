(function () {
    const TAB_ID_KEY = 'dp_tab_id';
    const ACTIVE_TABS_KEY = 'dp_active_tabs';
    const LOGOUT_PENDING_KEY = 'dp_tab_logout_pending_at';
    const HEARTBEAT_MS = 2000;
    const LOGOUT_DELAY_MS = 1500;
    const STALE_MS = 8000;

    function storageGet(key, fallback) {
        try {
            const value = localStorage.getItem(key);
            return value !== null ? value : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function getTabId() {
        let tabId = sessionStorage.getItem(TAB_ID_KEY);

        if (!tabId) {
            tabId = 'tab_' + Date.now() + '_' + Math.random().toString(36).slice(2, 11);
            sessionStorage.setItem(TAB_ID_KEY, tabId);
        }

        return tabId;
    }

    const tabId = getTabId();

    function getActiveTabs() {
        try {
            const raw = storageGet(ACTIVE_TABS_KEY, '{}');
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function setActiveTabs(tabs) {
        try {
            localStorage.setItem(ACTIVE_TABS_KEY, JSON.stringify(tabs));
        } catch (error) {
            // Ignore storage errors.
        }
    }

    function pruneStaleTabs(tabs) {
        const now = Date.now();
        const pruned = {};

        Object.keys(tabs).forEach(function (id) {
            if (now - tabs[id] < STALE_MS) {
                pruned[id] = tabs[id];
            }
        });

        return pruned;
    }

    function countActiveTabs() {
        return Object.keys(pruneStaleTabs(getActiveTabs())).length;
    }

    function registerTab() {
        const tabs = pruneStaleTabs(getActiveTabs());
        tabs[tabId] = Date.now();
        setActiveTabs(tabs);
        cancelPendingLogout();
    }

    function unregisterTab() {
        const tabs = pruneStaleTabs(getActiveTabs());
        delete tabs[tabId];
        setActiveTabs(tabs);
        return Object.keys(tabs).length;
    }

    function cancelPendingLogout() {
        try {
            localStorage.removeItem(LOGOUT_PENDING_KEY);
        } catch (error) {
            // Ignore storage errors.
        }
    }

    function scheduleLogoutIfNoTabs() {
        if (countActiveTabs() > 0) {
            return;
        }

        try {
            localStorage.setItem(LOGOUT_PENDING_KEY, String(Date.now() + LOGOUT_DELAY_MS));
        } catch (error) {
            // Ignore storage errors.
        }

        window.setTimeout(function () {
            const pendingAt = parseInt(storageGet(LOGOUT_PENDING_KEY, '0'), 10);

            if (!pendingAt || Date.now() < pendingAt) {
                return;
            }

            if (countActiveTabs() > 0) {
                cancelPendingLogout();
                return;
            }

            cancelPendingLogout();

            if (navigator.sendBeacon) {
                navigator.sendBeacon('api/session_tab_logout.php');
            } else {
                fetch('api/session_tab_logout.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: true,
                });
            }

            try {
                localStorage.removeItem(ACTIVE_TABS_KEY);
            } catch (error) {
                // Ignore storage errors.
            }
        }, LOGOUT_DELAY_MS + 100);
    }

    registerTab();
    window.setInterval(registerTab, HEARTBEAT_MS);

    window.addEventListener('storage', function (event) {
        if (event.key === ACTIVE_TABS_KEY || event.key === LOGOUT_PENDING_KEY) {
            if (countActiveTabs() > 0) {
                cancelPendingLogout();
            }
        }
    });

    window.addEventListener('pagehide', function (event) {
        if (event.persisted) {
            return;
        }

        unregisterTab();
        scheduleLogoutIfNoTabs();
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            registerTab();
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            registerTab();
        }
    });
})();