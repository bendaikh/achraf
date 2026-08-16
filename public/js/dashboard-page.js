/**
 * Dashboard Alpine component — data loads asynchronously from /dashboard/data.
 * Filters (period, date range, chart range) stay in the URL so soft-nav history
 * and browser back restore the exact filtered view.
 */
window.dashboardPage = function (dataUrl, bootstrap) {
    const BRAND_BLUE = '#0a5d8a';
    const BRAND_GOLD = '#fdb819';

    const TONE_BG = {
        sky: 'bg-sky-50',
        emerald: 'bg-emerald-50',
        rose: 'bg-rose-50',
        indigo: 'bg-indigo-50',
        amber: 'bg-amber-50',
        violet: 'bg-violet-50',
        orange: 'bg-orange-50',
        slate: 'bg-slate-100',
    };

    const TONE_TEXT = {
        sky: 'text-sky-600',
        emerald: 'text-emerald-600',
        rose: 'text-rose-600',
        indigo: 'text-indigo-600',
        amber: 'text-amber-600',
        violet: 'text-violet-600',
        orange: 'text-orange-600',
        slate: 'text-slate-600',
    };

    const CHANNEL_BAR = {
        shopify: 'bg-emerald-500',
        pos: 'bg-[#0a5d8a]',
        jumia: 'bg-orange-500',
        direct: 'bg-[#fdb819]',
    };

    const METHOD_COLORS = {
        cash: '#10b981',
        card: '#0a5d8a',
        cheque: '#8b5cf6',
        transfer: '#fdb819',
        other: '#94a3b8',
    };

    const ICONS = {
        revenue: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
        cash_in: 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941',
        cash_out: 'M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306 6.43l.776 2.898m0 0l3.182-5.511m-3.182 5.51l-5.511-3.181',
        purchases: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z',
        expenses: 'M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185z',
        result: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        treasury: 'M2.25 6.75A2.25 2.25 0 014.5 4.5h15a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75zM16.5 12a.75.75 0 100 1.5.75.75 0 000-1.5z',
        receivables: 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        payables: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
        open_orders: 'M3.75 4.5h16.5v15H3.75v-15zM8.25 2.25v4.5m7.5-4.5v4.5M3.75 9h16.5',
    };

    const emptyList = { items: [], url: '#' };

    return {
        dataUrl,
        loading: false,
        error: null,
        dateFrom: bootstrap?.dateFrom || '',
        dateTo: bootstrap?.dateTo || '',
        period: bootstrap?.period || 'month',
        chartPeriod: bootstrap?.chartPeriod || '6',
        periodLabel: bootstrap?.periodLabel || '',
        previousPeriodLabel: bootstrap?.previousPeriodLabel || '',
        todayLabel: bootstrap?.todayLabel || '',
        kpis: bootstrap?.kpis || [],
        todo: bootstrap?.todo || { items: [], total: 0 },
        chart: bootstrap?.chart || { labels: [], revenue: [], cash_in: [], expenses: [], result: [] },
        channels: bootstrap?.channels || { total: 0, items: [] },
        paymentMethods: bootstrap?.paymentMethods || { total: 0, items: [] },
        activity: bootstrap?.activity || { items: [], average_basket: 0, orders_url: '#' },
        stock: bootstrap?.stock || { total: 0, stocked: 0, non_stocked: 0, services: 0, in_stock: 0, low_stock: 0, out_of_stock: 0, stock_value: 0, urls: {}, restock: [] },
        treasury: bootstrap?.treasury || { available: true, caisse: 0, banque: 0, other: 0, total: 0, in: 0, out: 0, net: 0, urls: {} },
        receivables: bootstrap?.receivables || { count: 0, total: 0, items: [], url: '#' },
        payables: bootstrap?.payables || { count: 0, total: 0, items: [], url: '#' },
        recent: bootstrap?.recent || { orders: emptyList, invoices: emptyList, payments: emptyList, movements: emptyList },
        financialChart: null,
        paymentChartInstance: null,
        _alive: true,
        _loadToken: 0,

        chartPeriods: [
            { value: '6', label: '6 mois' },
            { value: '12', label: '12 mois' },
            { value: 'year', label: 'Année' },
        ],

        get channelItems() {
            const items = this.channels?.items || [];
            return (this.channels?.total || 0) > 0 ? items.filter((item) => item.amount > 0) : [];
        },

        get paymentItems() {
            const items = this.paymentMethods?.items || [];
            return (this.paymentMethods?.total || 0) > 0 ? items.filter((item) => item.amount > 0) : [];
        },

        get balanceBlocks() {
            return [
                {
                    key: 'receivables',
                    title: 'Créances clients',
                    partyLabel: 'Client',
                    emptyLabel: 'Aucune créance en cours',
                    tone: 'text-orange-600',
                    data: this.receivables || { count: 0, total: 0, items: [], url: '#' },
                },
                {
                    key: 'payables',
                    title: 'Dettes fournisseurs',
                    partyLabel: 'Fournisseur',
                    emptyLabel: 'Aucune dette en cours',
                    tone: 'text-rose-600',
                    data: this.payables || { count: 0, total: 0, items: [], url: '#' },
                },
            ];
        },

        get recentLists() {
            const recent = this.recent || {};
            return [
                { key: 'orders', title: 'Dernières commandes', emptyLabel: 'Aucune commande', amountTone: 'text-gray-900', data: recent.orders || emptyList },
                { key: 'invoices', title: 'Dernières factures', emptyLabel: 'Aucune facture', amountTone: 'text-gray-900', data: recent.invoices || emptyList },
                { key: 'payments', title: 'Derniers paiements', emptyLabel: 'Aucun paiement', amountTone: 'text-emerald-600', data: recent.payments || emptyList },
                { key: 'movements', title: 'Derniers mouvements de trésorerie', emptyLabel: 'Aucun mouvement', amountTone: 'text-gray-900', data: recent.movements || emptyList },
            ];
        },

        money(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) {
                return '—';
            }
            return Number(value).toLocaleString('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }) + ' DH';
        },

        number(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) {
                return '—';
            }
            return Number(value).toLocaleString('fr-FR');
        },

        percent(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) {
                return '—';
            }
            return Number(value).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' %';
        },

        signedPercent(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) {
                return '—';
            }
            const sign = Number(value) >= 0 ? '+' : '';
            return sign + this.percent(value);
        },

        toneBg(tone) {
            return TONE_BG[tone] || TONE_BG.slate;
        },

        toneText(tone) {
            return TONE_TEXT[tone] || TONE_TEXT.slate;
        },

        channelBar(key) {
            return CHANNEL_BAR[key] || 'bg-gray-400';
        },

        methodColor(key) {
            return METHOD_COLORS[key] || METHOD_COLORS.other;
        },

        iconPath(key) {
            return ICONS[key] || ICONS.result;
        },

        async init() {
            const started = performance.now();
            if (bootstrap?.kpis) {
                this.applyPayload(bootstrap);
                await this.renderCharts();
                this.recordMetric('dashboard-bootstrap-ms', performance.now() - started);
                return;
            }
            await this.loadData({ historyMode: 'none' });
            this.recordMetric('dashboard-initial-load-ms', performance.now() - started);
        },

        destroy() {
            this._alive = false;
            this._loadToken += 1;
            this.destroyCharts();
        },

        destroyCharts() {
            [this.financialChart, this.paymentChartInstance].forEach((chart) => {
                if (!chart) return;
                try {
                    chart.destroy();
                } catch (e) {}
            });
            this.financialChart = null;
            this.paymentChartInstance = null;
        },

        async applyFilter() {
            await this.loadData({ historyMode: 'push' });
        },

        async refresh() {
            await this.loadData({ historyMode: 'replace' });
        },

        async selectPeriod(period) {
            this.period = period;
            if (period !== 'custom') {
                // Le serveur recalcule les bornes : on laisse les champs se resynchroniser.
                this.dateFrom = '';
                this.dateTo = '';
            }
            await this.loadData({ historyMode: 'push' });
        },

        async selectChartPeriod(value) {
            this.chartPeriod = value;
            await this.loadData({ historyMode: 'push' });
        },

        buildQuery(url) {
            if (this.period) url.searchParams.set('period', this.period);
            if (this.dateFrom) url.searchParams.set('date_from', this.dateFrom);
            if (this.dateTo) url.searchParams.set('date_to', this.dateTo);
            if (this.chartPeriod) url.searchParams.set('chart_period', this.chartPeriod);
            return url;
        },

        async loadData({ historyMode = 'none' } = {}) {
            const token = ++this._loadToken;
            this.loading = true;
            this.error = null;
            const fetchStarted = performance.now();

            try {
                const url = this.buildQuery(new URL(this.dataUrl, window.location.origin));

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!this._alive || token !== this._loadToken) {
                    return;
                }

                if (!response.ok) {
                    throw new Error('Impossible de charger le tableau de bord.');
                }

                const payload = await response.json();
                if (!this._alive || token !== this._loadToken) {
                    return;
                }

                this.applyPayload(payload);
                await this.renderCharts();
                this.recordMetric('dashboard-data-fetch-ms', performance.now() - fetchStarted);
                this.syncHistory(payload.url, historyMode);
            } catch (e) {
                if (!this._alive || token !== this._loadToken) {
                    return;
                }
                this.error = e.message || 'Erreur de chargement.';
            } finally {
                if (token === this._loadToken) {
                    this.loading = false;
                }
            }
        },

        /**
         * Keep the address bar in sync with the active filters.
         * pushState uses the soft-nav state shape so browser back re-renders the
         * previously filtered dashboard through the existing soft-nav popstate handler.
         */
        syncHistory(nextUrl, mode) {
            if (mode === 'none' || !nextUrl) {
                return;
            }

            const target = new URL(nextUrl, window.location.origin).toString();
            if (target === window.location.href) {
                return;
            }

            const state = { softNav: true, url: target, scrollY: 0 };

            try {
                if (mode === 'push') {
                    window.history.pushState(state, '', target);
                } else {
                    window.history.replaceState(state, '', target);
                }
            } catch (e) {}
        },

        applyPayload(payload) {
            this.dateFrom = payload.dateFrom || this.dateFrom;
            this.dateTo = payload.dateTo || this.dateTo;
            this.period = payload.period || this.period;
            this.chartPeriod = payload.chartPeriod || this.chartPeriod;
            this.periodLabel = payload.periodLabel || this.periodLabel;
            this.previousPeriodLabel = payload.previousPeriodLabel || this.previousPeriodLabel;
            this.todayLabel = payload.todayLabel || this.todayLabel;
            this.kpis = payload.kpis || [];
            this.todo = payload.todo || { items: [], total: 0 };
            this.chart = payload.chart || { labels: [], revenue: [], cash_in: [], expenses: [], result: [] };
            this.channels = payload.channels || { total: 0, items: [] };
            this.paymentMethods = payload.paymentMethods || { total: 0, items: [] };
            this.activity = payload.activity || { items: [], average_basket: 0, orders_url: '#' };
            this.stock = payload.stock || this.stock;
            this.treasury = payload.treasury || this.treasury;
            this.receivables = payload.receivables || { count: 0, total: 0, items: [], url: '#' };
            this.payables = payload.payables || { count: 0, total: 0, items: [], url: '#' };
            this.recent = payload.recent || { orders: emptyList, invoices: emptyList, payments: emptyList, movements: emptyList };
        },

        async ensureChartJs() {
            if (window.Chart) return;
            await window.SoftNav?.loadAsset?.({
                type: 'script',
                src: 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            });
        },

        canvasReady(canvas) {
            return !!(canvas
                && canvas.isConnected
                && typeof canvas.getContext === 'function'
                && canvas.getContext('2d'));
        },

        async renderCharts() {
            if (!this._alive) return;

            await this.ensureChartJs();
            if (!this._alive || !window.Chart) return;

            await this.$nextTick();
            if (!this._alive) return;

            this.destroyCharts();
            this.renderFinancialChart();
            this.renderPaymentChart();
        },

        renderFinancialChart() {
            const canvas = this.$refs.financialCanvas;
            if (!this.canvasReady(canvas)) return;

            const currency = (value) => Number(value || 0).toLocaleString('fr-FR') + ' DH';

            try {
                this.financialChart = new Chart(canvas, {
                    data: {
                        labels: this.chart.labels || [],
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Chiffre d\'affaires',
                                data: this.chart.revenue || [],
                                backgroundColor: BRAND_GOLD,
                                borderRadius: 4,
                                order: 3,
                            },
                            {
                                type: 'bar',
                                label: 'Encaissements',
                                data: this.chart.cash_in || [],
                                backgroundColor: '#10b981',
                                borderRadius: 4,
                                order: 3,
                            },
                            {
                                type: 'bar',
                                label: 'Dépenses',
                                data: this.chart.expenses || [],
                                backgroundColor: '#ef4444',
                                borderRadius: 4,
                                order: 3,
                            },
                            {
                                type: 'line',
                                label: 'Résultat',
                                data: this.chart.result || [],
                                borderColor: BRAND_BLUE,
                                backgroundColor: BRAND_BLUE,
                                borderWidth: 2,
                                tension: 0.3,
                                pointRadius: 3,
                                order: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
                            tooltip: {
                                callbacks: {
                                    label: (context) => `${context.dataset.label} : ${currency(context.parsed.y)}`,
                                },
                            },
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                            y: {
                                beginAtZero: true,
                                ticks: { font: { size: 10 }, callback: (v) => currency(v) },
                            },
                        },
                    },
                });
            } catch (e) {
                console.warn('[Dashboard] financial chart failed', e);
            }
        },

        renderPaymentChart() {
            const canvas = this.$refs.paymentCanvas;
            if (!this.canvasReady(canvas)) return;

            const items = this.paymentItems;
            if (!items.length) return;

            try {
                this.paymentChartInstance = new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: items.map((item) => item.label),
                        datasets: [{
                            data: items.map((item) => item.amount),
                            backgroundColor: items.map((item) => this.methodColor(item.key)),
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        cutout: '62%',
                        plugins: { legend: { display: false } },
                    },
                });
            } catch (e) {
                console.warn('[Dashboard] payment chart failed', e);
            }
        },

        recordMetric(name, ms) {
            window.__navMetrics = window.__navMetrics || {};
            window.__navMetrics[name] = Math.round(ms);
            if (window.SoftNav?.logMetric) {
                window.SoftNav.logMetric(name, ms);
            }
        },
    };
};
