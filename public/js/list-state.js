(function () {
    'use strict';

    var STORAGE_KEY = 'lm-list-states';
    var LIVE_SEARCH_FOCUS_KEY = 'lm-live-search-focus';
    var liveSearchTimer = null;
    var liveSearchComposing = false;
    var lastLiveSearchUrl = '';
    var liveSearchDraft = null;
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

    function persistListUrl(url, pathname) {
        if (!pathname) {
            try {
                pathname = new URL(url, window.location.origin).pathname;
            } catch (e) {
                pathname = currentListPath();
            }
        }
        var absolute;
        try {
            absolute = new URL(url, window.location.origin);
        } catch (e) {
            return;
        }
        var storedUrl = absolute.pathname + absolute.search;
        if (isBareListUrl(absolute)) {
            clearSaved(pathname);
            return;
        }
        var states = readStates();
        states[pathname] = {
            url: storedUrl,
            scroll: getScrollY(),
            updatedAt: Date.now()
        };
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
        persistListUrl(currentListUrl(), currentListPath());
    }

    function getScrollY() {
        var shell = document.querySelector('.app-shell-main');
        if (shell && shell.scrollHeight > shell.clientHeight + 1) {
            return shell.scrollTop || 0;
        }
        var pageMain = document.querySelector('#app-page-root > main');
        if (pageMain && pageMain.scrollHeight > pageMain.clientHeight + 1) {
            return pageMain.scrollTop || 0;
        }
        return window.scrollY || window.pageYOffset || 0;
    }

    function setScrollY(y) {
        var shell = document.querySelector('.app-shell-main');
        if (shell && shell.scrollHeight > shell.clientHeight + 1) {
            shell.scrollTop = y;
            return;
        }
        var pageMain = document.querySelector('#app-page-root > main');
        if (pageMain && pageMain.scrollHeight > pageMain.clientHeight + 1) {
            pageMain.scrollTop = y;
            return;
        }
        window.scrollTo(0, y);
    }

    function restoreScroll(saved) {
        if (!saved || typeof saved.scroll !== 'number') {
            return;
        }
        window.setTimeout(function () {
            setScrollY(saved.scroll);
        }, 30);
    }

    function navigateTo(url, replace) {
        if (window.SoftNav && typeof window.SoftNav.navigate === 'function') {
            if (replace) {
                try {
                    window.history.replaceState(
                        { softNav: true, url: url, scrollY: getScrollY() },
                        '',
                        url
                    );
                } catch (e) {}
                window.SoftNav.navigate(url, { pushState: false, fromPopState: true, keepScroll: true });
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
        // Module tabs are intentional destinations (e.g. Liste des produits vs Services).
        if (link.closest('#app-module-tabs')) {
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
            clearLiveSearchDraft();
            try {
                var resetUrl = new URL(link.href, window.location.origin);
                clearSaved(resetUrl.pathname);
            } catch (e) {}
            skipPersistOnce = true;
            return;
        }
        try {
            var leaveUrl = new URL(link.href, window.location.origin);
            if (leaveUrl.origin === window.location.origin && leaveUrl.pathname !== currentListPath()) {
                clearLiveSearchDraft();
            }
        } catch (e) {}
        persistCurrentList();
        rewriteListLink(link);
    }

    function formUrlFrom(form) {
        var action = form.getAttribute('action') || window.location.href;
        var url = new URL(action, window.location.origin);
        url.search = '';
        var data = new FormData(form);
        data.forEach(function (value, key) {
            if (key === 'page') {
                return;
            }
            if (value !== '') {
                url.searchParams.append(key, String(value));
            }
        });
        return url.toString();
    }

    function isLiveSearchInput(input) {
        if (!(input instanceof HTMLInputElement)) {
            return false;
        }
        if (input.type === 'hidden' || input.type === 'date' || input.type === 'number') {
            return false;
        }
        var name = input.getAttribute('name');
        return name === 'search' || name === 'q';
    }

    function clearLiveSearchDraft() {
        liveSearchDraft = null;
        try {
            sessionStorage.removeItem(LIVE_SEARCH_FOCUS_KEY);
        } catch (e) {}
    }

    function updateLiveSearchDraft(input) {
        if (!isLiveSearchInput(input)) {
            return;
        }
        var form = input.closest('form');
        liveSearchDraft = {
            name: input.getAttribute('name') || 'search',
            id: input.id || '',
            value: input.value,
            caret: typeof input.selectionStart === 'number' ? input.selectionStart : input.value.length,
            path: form ? listPathFromForm(form) : window.location.pathname,
            scrollY: getScrollY()
        };
        try {
            sessionStorage.setItem(LIVE_SEARCH_FOCUS_KEY, JSON.stringify(liveSearchDraft));
        } catch (e) {}
    }

    function rememberLiveSearchScroll() {
        if (!liveSearchDraft) {
            return;
        }
        liveSearchDraft.scrollY = getScrollY();
        try {
            sessionStorage.setItem(LIVE_SEARCH_FOCUS_KEY, JSON.stringify(liveSearchDraft));
        } catch (e) {}
    }

    function findLiveSearchInput(state) {
        if (!state) {
            return null;
        }
        var root = document.getElementById('app-page-root') || document;
        var input = null;
        if (state.id) {
            try {
                input = root.querySelector('#' + (window.CSS && CSS.escape ? CSS.escape(state.id) : state.id));
            } catch (e) {
                input = document.getElementById(state.id);
            }
        }
        if (!input && state.name) {
            input = root.querySelector('form[data-list-page] input[name="' + state.name + '"]')
                || root.querySelector('input[name="' + state.name + '"]');
        }
        return input instanceof HTMLInputElement ? input : null;
    }

    function restoreSearchCaret() {
        var state = liveSearchDraft;
        if (!state) {
            var raw;
            try {
                raw = sessionStorage.getItem(LIVE_SEARCH_FOCUS_KEY);
            } catch (e) {
                return;
            }
            if (!raw) {
                return;
            }
            try {
                state = JSON.parse(raw);
            } catch (e) {
                return;
            }
            liveSearchDraft = state;
        }

        if (state.path && state.path !== currentListPath()) {
            clearLiveSearchDraft();
            return;
        }

        var input = findLiveSearchInput(state);
        if (!input) {
            return;
        }

        // SoftNav replaces the whole page; re-apply what the user typed so
        // characters entered while the request was in flight are not lost.
        if (typeof state.value === 'string' && input.value !== state.value) {
            input.value = state.value;
        }

        // Keep the user's scroll position: focusing the search field would
        // otherwise scroll the page back up to the filter card.
        var keepY = typeof state.scrollY === 'number' ? state.scrollY : getScrollY();
        try {
            input.focus({ preventScroll: true });
        } catch (e) {
            input.focus();
        }
        var pos = Math.min(
            typeof state.caret === 'number' ? state.caret : input.value.length,
            input.value.length
        );
        try {
            input.setSelectionRange(pos, pos);
        } catch (e) {}

        // Only re-run live search when the draft term is ahead of the URL.
        // formUrlFrom() strips `page`, so comparing full URLs would bounce
        // pagination (e.g. ?page=2) back to page 1 whenever a search draft exists.
        var paramName = state.name || 'search';
        var applied = '';
        try {
            applied = new URL(window.location.href).searchParams.get(paramName) || '';
        } catch (e) {
            applied = '';
        }
        var draftAhead = (state.value || '') !== applied;
        if (draftAhead) {
            // Mid live-search refresh: keep the scroll the user had while typing.
            setScrollY(keepY);
            window.requestAnimationFrame(function () {
                setScrollY(keepY);
            });
        }

        if (!draftAhead) {
            return;
        }

        var form = input.closest('form');
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        window.clearTimeout(liveSearchTimer);
        liveSearchTimer = window.setTimeout(function () {
            var currentInput = findLiveSearchInput(liveSearchDraft || state);
            var currentForm = currentInput && currentInput.closest('form');
            if (currentForm && currentInput) {
                submitLiveSearch(currentForm, currentInput);
            }
        }, 100);
    }

    function submitLiveSearch(form, input) {
        updateLiveSearchDraft(input);
        var url = formUrlFrom(form);
        var next;
        try {
            next = new URL(url, window.location.origin);
        } catch (e) {
            return;
        }
        var nextKey = next.pathname + next.search;
        if (url === lastLiveSearchUrl && (window.location.pathname + window.location.search) === nextKey) {
            return;
        }
        lastLiveSearchUrl = url;
        // Persist the destination (cleared search = bare URL → drop saved filters),
        // not the current URL. Otherwise maybeRestoreList brings the old search back.
        persistListUrl(url, listPathFromForm(form));
        skipPersistOnce = true;
        if (window.SoftNav && typeof window.SoftNav.navigate === 'function') {
            window.SoftNav.navigate(url, { replaceState: true, keepScroll: true, pushState: false, silent: true });
            return;
        }
        window.location.href = url;
    }

    function onSearchInput(event) {
        if (liveSearchComposing) {
            return;
        }
        var input = event.target;
        if (!isLiveSearchInput(input)) {
            return;
        }
        var form = input.closest('form');
        if (!(form instanceof HTMLFormElement) || (form.getAttribute('method') || 'GET').toUpperCase() !== 'GET') {
            return;
        }
        if (!form.hasAttribute('data-list-page')) {
            form.setAttribute('data-list-page', '');
        }
        updateLiveSearchDraft(input);
        window.clearTimeout(liveSearchTimer);
        liveSearchTimer = window.setTimeout(function () {
            // Always resolve from the live DOM — SoftNav may have replaced the form.
            var currentInput = findLiveSearchInput(liveSearchDraft || {
                name: input.getAttribute('name'),
                id: input.id || ''
            });
            if (!currentInput) {
                return;
            }
            if (liveSearchDraft && typeof liveSearchDraft.value === 'string' && currentInput.value !== liveSearchDraft.value) {
                currentInput.value = liveSearchDraft.value;
            }
            var currentForm = currentInput.closest('form');
            if (!(currentForm instanceof HTMLFormElement)) {
                return;
            }
            if (!currentForm.hasAttribute('data-list-page')) {
                currentForm.setAttribute('data-list-page', '');
            }
            submitLiveSearch(currentForm, currentInput);
        }, 300);
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
        var url = formUrlFrom(form);
        persistListUrl(url, listPathFromForm(form));
        skipPersistOnce = true;
        if (!window.SoftNav || typeof window.SoftNav.navigate !== 'function') {
            return;
        }
        event.preventDefault();
        window.SoftNav.navigate(url);
    }

    function init() {
        patchSoftNav();
        maybeRestoreList();
    }

    document.addEventListener('click', onClickCapture, true);
    document.addEventListener('submit', onSubmit, true);
    document.addEventListener('input', onSearchInput, true);
    document.addEventListener('compositionstart', function (event) {
        if (isLiveSearchInput(event.target)) {
            liveSearchComposing = true;
        }
    }, true);
    document.addEventListener('compositionend', function (event) {
        liveSearchComposing = false;
        onSearchInput(event);
    }, true);
    window.addEventListener('pagehide', persistCurrentList);
    window.addEventListener('beforeunload', persistCurrentList);
    // capture:true so we also see scroll on overflow containers (app shell / main)
    document.addEventListener('scroll', rememberLiveSearchScroll, { passive: true, capture: true });
    window.addEventListener('soft-nav:loaded', function () {
        patchSoftNav();
        // While live search is active, do not restore a stale saved scroll
        // (captured when the request started) — that jumps the user back up.
        if (liveSearchDraft) {
            persistCurrentList();
        } else {
            maybeRestoreList();
        }
        restoreSearchCaret();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
