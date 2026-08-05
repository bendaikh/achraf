/**
 * Soft navigation shell — keeps sidebar/header mounted and swaps page content.
 * Intercepts [data-soft-nav] links and same-origin in-app links inside the shell.
 */
(function () {
    const HEADER = 'X-Soft-Navigation';
    const loadedAssets = new Set();
    const IGNORE_PATH = /\/(print|pdf|export|download)(\/|$)/i;
    const IGNORE_TEMPLATE = /\/import\/template/i;
    // True while injected page scripts run (before HTML is mounted into #app-page-root).
    let mounting = false;
    let mountReadyQueue = [];

    /**
     * Run after the current page DOM is available.
     * Soft-nav executes scripts before mount, so DOMContentLoaded never fires again —
     * queue callbacks and flush them right after #app-page-root is filled.
     */
    function whenReady(fn) {
        if (typeof fn !== 'function') {
            return;
        }
        if (mounting) {
            mountReadyQueue.push(fn);
            return;
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
            return;
        }
        fn();
    }

    function flushMountReadyQueue() {
        const queued = mountReadyQueue;
        mountReadyQueue = [];
        queued.forEach(function (fn) {
            try {
                fn();
            } catch (error) {
                console.error('[SoftNav] whenReady callback failed', error);
            }
        });
    }

    /**
     * Soft-nav runs page scripts before HTML is in #app-page-root.
     * jQuery $(fn) / $(document).ready(fn) would fire immediately (document already
     * ready) and miss elements — route them through whenReady instead.
     */
    function patchJQueryReady() {
        if (!window.jQuery || window.jQuery.__softNavReadyPatched) {
            return;
        }
        var $ = window.jQuery;
        $.__softNavReadyPatched = true;
        var originalFnReady = $.fn.ready;
        $.fn.ready = function (fn) {
            if (typeof fn === 'function') {
                whenReady(fn);
                return this;
            }
            return originalFnReady.apply(this, arguments);
        };
    }

    patchJQueryReady();

    function pageRoot() {
        return document.getElementById('app-page-root');
    }

    function pageTitleEl() {
        return document.getElementById('app-page-title');
    }

    function tabsEl() {
        return document.getElementById('app-module-tabs');
    }

    function logMetric(name, ms) {
        window.__navMetrics = window.__navMetrics || {};
        window.__navMetrics[name] = Math.round(ms);
        if (window.__SOFT_NAV_DEBUG) {
            console.info('[SoftNav]', name, Math.round(ms) + 'ms');
        }
    }

    function loadAsset(asset) {
        if (!asset || (asset.type !== 'script' && asset.type !== 'style')) {
            return Promise.resolve();
        }

        // Inline scripts must run for each page (they define page-local Alpine helpers).
        if (asset.type === 'script' && asset.content && !asset.src) {
            return new Promise(function (resolve) {
                const script = document.createElement('script');
                script.textContent = asset.content;
                document.head.appendChild(script);
                script.remove();
                resolve();
            });
        }

        if (!asset.src) {
            return Promise.resolve();
        }

        const key = asset.type + ':' + asset.src;
        if (loadedAssets.has(key) || document.querySelector(asset.type === 'style'
            ? 'link[href="' + asset.src + '"]'
            : 'script[src="' + asset.src + '"]')) {
            loadedAssets.add(key);
            return Promise.resolve();
        }

        return new Promise(function (resolve, reject) {
            if (asset.type === 'style') {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = asset.src;
                link.onload = function () {
                    loadedAssets.add(key);
                    resolve();
                };
                link.onerror = reject;
                document.head.appendChild(link);
                return;
            }

            const script = document.createElement('script');
            script.src = asset.src;
            script.async = false;
            script.onload = function () {
                loadedAssets.add(key);
                resolve();
            };
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async function loadAssets(assets) {
        const list = Array.isArray(assets) ? assets : [];
        for (let i = 0; i < list.length; i++) {
            await loadAsset(list[i]);
        }
    }

    function isSoftNavableUrl(href) {
        let url;
        try {
            url = new URL(href, window.location.origin);
        } catch (e) {
            return false;
        }

        if (url.origin !== window.location.origin) return false;
        if (url.pathname === '/login' || url.pathname === '/logout') return false;
        if (IGNORE_PATH.test(url.pathname)) return false;
        if (IGNORE_TEMPLATE.test(url.pathname)) return false;
        if (/\.(pdf|zip|xlsx|csv|xml)(\?|$)/i.test(url.pathname)) return false;

        return true;
    }

    function shouldSoftNav(link) {
        if (!link || link.tagName !== 'A') return false;
        if (link.hasAttribute('data-soft-nav-ignore')) return false;
        if (link.hasAttribute('download')) return false;
        if (link.target && link.target !== '' && link.target !== '_self') return false;

        const href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0) {
            return false;
        }

        if (!isSoftNavableUrl(link.href)) return false;

        if (link.hasAttribute('data-soft-nav')) return true;

        return !!link.closest('.app-shell-aside, .app-shell-main, #app-module-tabs, #app-page-root');
    }

    function loadingEl() {
        return document.getElementById('soft-nav-loading');
    }

    function showLoading() {
        const root = pageRoot();
        const overlay = loadingEl();
        if (root) {
            root.setAttribute('aria-busy', 'true');
            root.classList.add('pointer-events-none');
        }
        if (overlay) {
            overlay.hidden = false;
        }
    }

    function hideLoading() {
        const root = pageRoot();
        const overlay = loadingEl();
        if (root) {
            root.removeAttribute('aria-busy');
            root.classList.remove('pointer-events-none', 'opacity-60');
        }
        if (overlay) {
            overlay.hidden = true;
        }
    }

    function updateSidebarActive(moduleKey, url) {
        const links = document.querySelectorAll('[data-nav-module]');
        links.forEach(function (link) {
            const active = (moduleKey && link.getAttribute('data-nav-module') === moduleKey)
                || (url && link.href && link.href.split('?')[0] === String(url).split('?')[0]);
            link.classList.toggle('is-active', active);
            // Legacy class cleanup in case an older shell is still mounted.
            link.classList.remove('bg-[#fdb819]', 'text-white', 'shadow-sm', 'text-gray-700');
            if (active) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }

            const marker = link.querySelector('[data-nav-active-marker]');
            if (marker) {
                marker.style.display = active ? '' : 'none';
            }
        });
    }

    function destroyWidgets(root) {
        if (!root) return;

        if (window.jQuery) {
            try {
                window.jQuery(root).find('select.select2-hidden-accessible').each(function () {
                    try {
                        window.jQuery(this).select2('destroy');
                    } catch (e) {}
                });
            } catch (e) {}
        }

        document.querySelectorAll('.select2-container--open').forEach(function (el) {
            el.remove();
        });
    }

    function destroyCharts(root) {
        if (!root || !window.Chart || typeof window.Chart.getChart !== 'function') {
            return;
        }

        root.querySelectorAll('canvas').forEach(function (canvas) {
            try {
                const chart = window.Chart.getChart(canvas);
                if (chart) {
                    chart.destroy();
                }
            } catch (e) {}
        });
    }

    function destroyCurrentPage(root) {
        if (!root) return;
        destroyWidgets(root);
        destroyCharts(root);
        if (window.Alpine && typeof window.Alpine.destroyTree === 'function') {
            try {
                window.Alpine.destroyTree(root);
            } catch (e) {}
        }
    }

    function initInjectedScripts(root) {
        const scripts = Array.prototype.slice.call(root.querySelectorAll('script'));

        return scripts.reduce(function (chain, oldScript) {
            return chain.then(function () {
                return new Promise(function (resolve) {
                    const type = (oldScript.getAttribute('type') || '').toLowerCase();
                    if (type && type !== 'text/javascript' && type !== 'application/javascript') {
                        resolve();
                        return;
                    }

                    const script = document.createElement('script');
                    Array.prototype.forEach.call(oldScript.attributes || [], function (attr) {
                        script.setAttribute(attr.name, attr.value);
                    });

                    if (oldScript.src) {
                        script.async = false;
                        script.onload = function () {
                            oldScript.remove();
                            resolve();
                        };
                        script.onerror = function () {
                            oldScript.remove();
                            resolve();
                        };
                        document.head.appendChild(script);
                        return;
                    }

                    script.textContent = oldScript.textContent || '';
                    document.head.appendChild(script);
                    script.remove();
                    oldScript.remove();
                    resolve();
                });
            });
        }, Promise.resolve());
    }

    async function mountHtml(root, html) {
        destroyCurrentPage(root);
        document.documentElement.classList.remove('pos-full-view-active');

        // Parse off-document first so Alpine's MutationObserver does not evaluate
        // x-data (e.g. posRegister) before page scripts have defined those helpers.
        const holder = document.createElement('div');
        holder.innerHTML = html;
        mounting = true;
        mountReadyQueue = [];
        try {
            await initInjectedScripts(holder);

            root.replaceChildren.apply(root, Array.prototype.slice.call(holder.childNodes));

            if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                window.Alpine.initTree(root);
            }
        } finally {
            mounting = false;
            flushMountReadyQueue();
        }
    }

    async function navigate(url, { pushState = true, fromPopState = false } = {}) {
        const root = pageRoot();
        if (!root) {
            window.location.href = url;
            return;
        }

        const started = performance.now();
        showLoading();

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    [HEADER]: '1',
                },
                credentials: 'same-origin',
            });

            if (response.redirected && response.url && /\/login(\?|$)/.test(response.url)) {
                window.location.href = response.url;
                return;
            }

            if (!response.ok) {
                throw new Error('Soft navigation failed with status ' + response.status);
            }

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                window.location.href = url;
                return;
            }

            const payload = await response.json();
            await loadAssets(payload.assets || []);

            const titleEl = pageTitleEl();
            if (titleEl && payload.page_title) {
                titleEl.textContent = payload.page_title;
            }
            if (payload.title) {
                document.title = payload.title;
            }

            const tabs = tabsEl();
            if (tabs) {
                tabs.innerHTML = payload.tabs_html || '';
                tabs.hidden = !payload.tabs_html;
            }

            await mountHtml(root, payload.html || '');
            updateSidebarActive(payload.module || null, payload.url || url);

            if (pushState && !fromPopState) {
                window.history.pushState({ softNav: true, url: payload.url || url }, '', payload.url || url);
            }

            logMetric('soft-nav-total-ms', performance.now() - started);
            window.dispatchEvent(new CustomEvent('soft-nav:loaded', { detail: payload }));
        } catch (error) {
            console.error('[SoftNav]', error);
            window.location.href = url;
        } finally {
            hideLoading();
        }
    }

    function onClick(event) {
        if (event.defaultPrevented) return;
        if (event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!shouldSoftNav(link)) return;

        event.preventDefault();
        navigate(link.href);
    }

    function onPopState(event) {
        if (event.state && event.state.softNav && event.state.url) {
            navigate(event.state.url, { pushState: false, fromPopState: true });
            return;
        }
        if (event.state && event.state.softNav === false) {
            window.location.reload();
        }
    }

    document.addEventListener('click', onClick);
    window.addEventListener('popstate', onPopState);

    if (!window.history.state || window.history.state.softNav === undefined) {
        window.history.replaceState({ softNav: false, url: window.location.href }, '', window.location.href);
    }

    if (window.Chart) {
        loadedAssets.add('script:https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js');
    }

    window.SoftNav = {
        navigate,
        loadAsset,
        loadAssets,
        logMetric,
        HEADER,
        shouldSoftNav,
        whenReady,
    };
})();
