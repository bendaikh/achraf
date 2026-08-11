(function () {
    'use strict';

    var STORAGE_KEY = 'lm-list-states';
    var restoring = false;
    var skipPersistOnce = false;

    function readStates() {
        try {
            return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '{}');
        } catch (e) {
            return {};
        }
    }

    function writeStates(states) {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(states));
        } catch (e) {}
    }

    function listPathFromForm(form) {
        try {
            return new URL(form.getAttribute('action') || window.location.href, window.location.origin).pathname;
        } catch (e) {
            return window.location.pathname;
        }
    }

    function findListForm() {
        return document.querySelector('#app-page-root form[data-list-page], form[data-list-page]');
    }

    function isListPage() {
        return !!findListForm();
    }

    function currentListPath() {
        var form = findListForm();
        return form ? listPathFromForm(form) : window.location.pathname;
    }

    function currentListUrl() {
        return window.location.pathname + window.location.search;
    }

    function isBareListUrl(url) {
        var params = url.searchParams;
        var keys = [];
        params.forEach(function (_value, key) {
            keys.push(key);
        });
        return keys.length === 0;
    }

    function savedHasQuery(saved) {
        if (!saved || !saved.url) {
            return false;
        }
        var qIndex = saved.url.indexOf('?');
        return qIndex !== -1 && saved.url.slice(qIndex + 1).length > 0;
    }

    function getSaved(pathname) {
        var states = readStates();
        return states[pathname] || null;
    }

    function clearSaved(pathname) {
        var states = readStates();
        delete states[pathname];
        writeStates(states);
    }

    function persistCurrentList() {
        if (skipPersistOnce) {
            skipPersistOnce = false;
            return;
        }
        if (!isListPage()) {
            return;
        }
        var pathname = currentListPath();
        var states = readStates();
        states[pathname] = {
            url: currentListUrl(),
            scroll: window.scrollY || 0,
            updatedAt: Date.now()
        };
        writeStates(states);
    }

    function restoreScroll(saved) {
        if (!saved || typeof saved.scroll !== 'number') {
            return;
        }
        window.setTimeout(function () {
            window.scrollTo(0, saved.scroll);
        }, 30);
    }

    function navigateTo(url, replace) {
        if (window.SoftNav && typeof window.SoftNav.navigate === 'function') {
            if (replace) {
                try {
                    window.history.replaceState(
                        { softNav: true, url: url, scrollY: 0 },
                        '',
                        url
                    );
                } catch (e) {}
                window.SoftNav.navigate(url, { pushState: false, fromPopState: true });
                return;
            }
            window.SoftNav.navigate(url);
            return;
        }
        if (replace) {
            window.location.replace(url);
            return;
        }
        window.location.href = url;
    }

    function maybeRestoreList() {
        if (!isListPage()) {
            return;
        }
        var pathname = currentListPath();
        var saved = getSaved(pathname);
        var current = new URL(window.location.href);

        if (restoring) {
            if (saved && saved.url === currentListUrl()) {
                restoreScroll(saved);
            }
            return;
        }

        if (saved && savedHasQuery(saved) && isBareListUrl(current) && saved.url !== currentListUrl()) {
            restoring = true;
            skipPersistOnce = true;
            navigateTo(saved.url, true);
            window.setTimeout(function () {
                restoring = false;
            }, 800);
            return;
        }

        persistCurrentList();
        if (saved && saved.url === currentListUrl()) {
            restoreScroll(saved);
        }
    }

    function rewriteListLink(link) {
        if (!link || link.hasAttribute('data-list-reset') || link.hasAttribute('download')) {
            return;
        }
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) {
            return;
        }
        var url;
        try {
            url = new URL(link.href, window.location.origin);
        } catch (e) {
            return;
        }
        if (url.origin !== window.location.origin) {
            return;
        }
        var saved = getSaved(url.pathname);
        if (saved && savedHasQuery(saved) && isBareListUrl(url)) {
            link.href = saved.url;
        }
    }

    function patchSoftNav() {
        if (!window.SoftNav || window.SoftNav.__listStatePatched) {
            return;
        }
        var original = window.SoftNav.navigate;
        window.SoftNav.navigate = function (url, options) {
            persistCurrentList();
            return original.call(window.SoftNav, url, options);
        };
        window.SoftNav.__listStatePatched = true;
    }

    function onClickCapture(event) {
        var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
        if (!link) {
            return;
        }
        if (link.hasAttribute('data-list-reset')) {
            try {
                var resetUrl = new URL(link.href, window.location.origin);
                clearSaved(resetUrl.pathname);
            } catch (e) {}
            skipPersistOnce = true;
            return;
        }
        persistCurrentList();
        rewriteListLink(link);
    }

    function onSubmit(event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (!form.hasAttribute('data-list-page')) {
            return;
        }
        if ((form.getAttribute('method') || 'GET').toUpperCase() !== 'GET') {
            return;
        }
        persistCurrentList();
        if (!window.SoftNav || typeof window.SoftNav.navigate !== 'function') {
            return;
        }
        event.preventDefault();
        var action = form.getAttribute('action') || window.location.href;
        var url = new URL(action, window.location.origin);
        url.search = '';
        var data = new FormData(form);
        data.forEach(function (value, key) {
            if (value !== '') {
                url.searchParams.append(key, String(value));
            }
        });
        window.SoftNav.navigate(url.toString());
    }

    function init() {
        patchSoftNav();
        maybeRestoreList();
    }

    document.addEventListener('click', onClickCapture, true);
    document.addEventListener('submit', onSubmit, true);
    window.addEventListener('pagehide', persistCurrentList);
    window.addEventListener('beforeunload', persistCurrentList);
    window.addEventListener('soft-nav:loaded', function () {
        patchSoftNav();
        maybeRestoreList();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
