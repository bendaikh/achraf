/**
 * Dashboard Alpine component — data loads asynchronously from /dashboard/data.
 */
window.dashboardPage = function (dataUrl, bootstrap) {
    const emptyStats = {};
    return {
        dataUrl,
        loading: false,
        error: null,
        dateFrom: bootstrap?.dateFrom || '',
        dateTo: bootstrap?.dateTo || '',
        todayLabel: bootstrap?.todayLabel || '',
        stats: bootstrap?.stats || emptyStats,
        chart: bootstrap?.chart || { labels: [], revenue: [], expenses: [] },
        paymentChart: bootstrap?.paymentChart || { labels: [], values: [] },
        recentOrders: bootstrap?.recentOrders || [],
        recentInvoices: bootstrap?.recentInvoices || [],
        unpaidInvoices: bootstrap?.unpaidInvoices || { count: 0, total: 0, items: [] },
        revenueChart: null,
        paymentChartInstance: null,
        _alive: true,
        _loadToken: 0,

        get countTiles() {
            const s = this.stats || {};
            return [
                { label: 'Clients', value: s.clients_count, color: 'text-blue-600' },
                { label: 'Fournisseurs', value: s.suppliers_count, color: 'text-indigo-600' },
                { label: 'Produits', value: s.products_count, color: 'text-emerald-600' },
                { label: 'Commandes', value: s.orders_total, color: 'text-amber-600' },
                { label: 'Factures', value: s.invoices_count, color: 'text-purple-600' },
                { label: 'Devis en cours', value: s.quotes_pending, color: 'text-gray-600' },
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

        async init() {
            const started = performance.now();
            if (bootstrap?.stats) {
                this.applyPayload(bootstrap);
                await this.renderCharts();
                this.recordMetric('dashboard-bootstrap-ms', performance.now() - started);
                return;
            }
            if (bootstrap) {
                this.dateFrom = bootstrap.dateFrom || this.dateFrom;
                this.dateTo = bootstrap.dateTo || this.dateTo;
                this.todayLabel = bootstrap.todayLabel || this.todayLabel;
            }
            await this.loadData({ replaceUrl: false });
            this.recordMetric('dashboard-initial-load-ms', performance.now() - started);
        },

        destroy() {
            this._alive = false;
            this._loadToken += 1;
            this.destroyCharts();
        },

        destroyCharts() {
            if (this.revenueChart) {
                try {
                    this.revenueChart.destroy();
                } catch (e) {}
                this.revenueChart = null;
            }
            if (this.paymentChartInstance) {
                try {
                    this.paymentChartInstance.destroy();
                } catch (e) {}
                this.paymentChartInstance = null;
            }
        },

        async applyFilter() {
            await this.loadData({ replaceUrl: true });
        },

        async loadData({ replaceUrl }) {
            const token = ++this._loadToken;
            this.loading = true;
            this.error = null;
            const fetchStarted = performance.now();

            try {
                const url = new URL(this.dataUrl, window.location.origin);
                if (this.dateFrom) url.searchParams.set('date_from', this.dateFrom);
                if (this.dateTo) url.searchParams.set('date_to', this.dateTo);

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

                if (replaceUrl) {
                    const next = new URL(window.location.href);
                    next.searchParams.set('date_from', payload.dateFrom);
                    next.searchParams.set('date_to', payload.dateTo);
                    window.history.replaceState(window.history.state, '', next.toString());
                }
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

        applyPayload(payload) {
            this.dateFrom = payload.dateFrom;
            this.dateTo = payload.dateTo;
            this.todayLabel = payload.todayLabel || this.todayLabel;
            this.stats = payload.stats || {};
            this.chart = payload.chart || { labels: [], revenue: [], expenses: [] };
            this.paymentChart = payload.paymentChart || { labels: [], values: [] };
            this.recentOrders = payload.recentOrders || [];
            this.recentInvoices = payload.recentInvoices || [];
            this.unpaidInvoices = payload.unpaidInvoices || { count: 0, total: 0, items: [] };
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

            // Wait until Alpine removed x-cloak and refs are in the live DOM.
            await this.$nextTick();
            if (!this._alive) return;

            this.destroyCharts();

            const revenueCanvas = this.$refs.revenueCanvas;
            const paymentWrap = this.$refs.paymentWrap;
            if (!this.canvasReady(revenueCanvas) || !paymentWrap || !paymentWrap.isConnected) {
                return;
            }

            const brandGold = '#fdb819';
            const brandGoldDark = '#e5a617';

            try {
                this.revenueChart = new Chart(revenueCanvas, {
                    type: 'bar',
                    data: {
                        labels: this.chart.labels || [],
                        datasets: [
                            {
                                label: 'Revenus',
                                data: this.chart.revenue || [],
                                backgroundColor: brandGold,
                                borderRadius: 4,
                            },
                            {
                                label: 'Dépenses',
                                data: this.chart.expenses || [],
                                backgroundColor: '#ef4444',
                                borderRadius: 4,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: { legend: { position: 'bottom' } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: (v) => v.toLocaleString('fr-FR') + ' DH' },
                            },
                        },
                    },
                });
            } catch (e) {
                console.warn('[Dashboard] revenue chart failed', e);
            }

            const paymentLabels = this.paymentChart.labels || [];
            const paymentValues = this.paymentChart.values || [];

            if (paymentLabels.length === 0) {
                paymentWrap.innerHTML = '<p class="text-sm text-gray-500">Aucune vente POS sur les 3 derniers mois</p>';
                return;
            }

            paymentWrap.innerHTML = '';
            const paymentCanvas = document.createElement('canvas');
            paymentWrap.appendChild(paymentCanvas);
            this.$refs.paymentCanvas = paymentCanvas;

            if (!this.canvasReady(paymentCanvas)) {
                return;
            }

            try {
                this.paymentChartInstance = new Chart(paymentCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: paymentLabels,
                        datasets: [{
                            data: paymentValues,
                            backgroundColor: [brandGold, brandGoldDark, '#0a5d8a', '#6b7280', '#10b981'],
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: { legend: { position: 'bottom' } },
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
