(function () {
    'use strict';

    var PENDING_EXPORTS_KEY = 'tableExportsPending';

    function getBar(exportType) {
        return document.getElementById('bulkActionsBar-' + exportType);
    }

    function getCheckboxes(exportType) {
        return document.querySelectorAll(
            'input.table-row-checkbox[data-export-type="' + exportType + '"]'
        );
    }

    function getSelectAll(exportType) {
        return document.querySelector(
            'input.table-select-all[data-export-type="' + exportType + '"]'
        );
    }

    function highlightRow(checkbox) {
        var row = checkbox.closest('tr');
        if (row) {
            row.classList.toggle('table-row-selected', checkbox.checked);
        }
    }

    window.updateTableSelectedCount = function (exportType) {
        var checked = document.querySelectorAll(
            'input.table-row-checkbox[data-export-type="' + exportType + '"]:checked'
        );
        var bar = getBar(exportType);
        var countEl = document.getElementById('selectedCount-' + exportType);
        var selectAll = getSelectAll(exportType);

        if (bar && countEl) {
            if (checked.length > 0) {
                bar.classList.remove('hidden');
                countEl.textContent = String(checked.length);
            } else {
                bar.classList.add('hidden');
                countEl.textContent = '0';
            }
        }

        if (selectAll) {
            var all = getCheckboxes(exportType);
            selectAll.checked = all.length > 0 && checked.length === all.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        }
    };

    window.toggleTableSelectAll = function (checkbox, exportType) {
        var isChecked = checkbox.checked;
        getCheckboxes(exportType).forEach(function (cb) {
            cb.checked = isChecked;
            highlightRow(cb);
        });
        updateTableSelectedCount(exportType);
    };

    window.clearTableSelection = function (exportType) {
        getCheckboxes(exportType).forEach(function (cb) {
            cb.checked = false;
            highlightRow(cb);
        });
        var selectAll = getSelectAll(exportType);
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        updateTableSelectedCount(exportType);
    };

    window.getSelectedTableIds = function (exportType) {
        return Array.from(
            document.querySelectorAll(
                'input.table-row-checkbox[data-export-type="' + exportType + '"]:checked'
            )
        ).map(function (cb) {
            return cb.value;
        });
    };

    function getCsrfToken() {
        var csrf = document.querySelector('meta[name="csrf-token"]');
        return csrf ? csrf.getAttribute('content') : '';
    }

    function readPendingExports() {
        try {
            return JSON.parse(localStorage.getItem(PENDING_EXPORTS_KEY) || '[]');
        } catch (error) {
            return [];
        }
    }

    function writePendingExports(exports) {
        localStorage.setItem(PENDING_EXPORTS_KEY, JSON.stringify(exports));
    }

    function rememberPendingExport(exportId, exportType, statusUrl) {
        var pending = readPendingExports().filter(function (item) {
            return String(item.exportId) !== String(exportId);
        });

        pending.push({
            exportId: exportId,
            exportType: exportType,
            statusUrl: statusUrl,
            startedAt: Date.now()
        });

        writePendingExports(pending);
    }

    function forgetPendingExport(exportId) {
        writePendingExports(readPendingExports().filter(function (item) {
            return String(item.exportId) !== String(exportId);
        }));
    }

    function ensureExportToast(exportId) {
        var id = 'tableExportToast-' + exportId;
        var existing = document.getElementById(id);
        if (existing) {
            return existing;
        }

        var toast = document.createElement('div');
        toast.id = id;
        toast.className = 'table-export-toast bg-white border border-blue-200 rounded-xl shadow-lg p-4 mb-3';
        toast.innerHTML = [
            '<div class="flex items-start justify-between gap-3">',
            '  <div>',
            '    <p class="text-sm font-semibold text-gray-900">Export en cours</p>',
            '    <p class="text-xs text-gray-500 mt-1" data-export-message>Votre export est généré en arrière-plan.</p>',
            '  </div>',
            '  <button type="button" class="text-gray-400 hover:text-gray-600" data-export-close aria-label="Fermer">×</button>',
            '</div>',
            '<div class="mt-3 h-2 bg-gray-100 rounded-full overflow-hidden">',
            '  <div class="h-full bg-blue-600 transition-all" style="width:0%" data-export-progress></div>',
            '</div>',
            '<div class="mt-3 hidden" data-export-download-wrap>',
            '  <a class="inline-flex items-center px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium" data-export-download>Télécharger l’export</a>',
            '</div>'
        ].join('');

        toast.querySelector('[data-export-close]').addEventListener('click', function () {
            toast.remove();
        });

        var container = ensureExportToastContainer();
        container.appendChild(toast);
        return toast;
    }

    function ensureExportToastContainer() {
        var container = document.getElementById('tableExportToastContainer');
        if (container) {
            return container;
        }

        container = document.createElement('div');
        container.id = 'tableExportToastContainer';
        container.className = 'fixed right-4 bottom-4 z-[60] flex flex-col items-end gap-3';
        document.body.appendChild(container);
        return container;
    }

    function updateExportToast(toast, message, progress) {
        var messageEl = toast.querySelector('[data-export-message]');
        var progressEl = toast.querySelector('[data-export-progress]');
        if (messageEl) {
            messageEl.textContent = message;
        }
        if (progressEl) {
            progressEl.style.width = Math.max(0, Math.min(100, progress || 0)) + '%';
        }
    }

    function triggerBackgroundDownload(url, filename) {
        var link = document.createElement('a');
        link.href = url;
        link.download = filename || '';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function pollExportStatus(exportId, exportType, statusUrl) {
        var toast = ensureExportToast(exportId);

        fetch(statusUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Impossible de suivre l’export.');
                }
                return response.json();
            })
            .then(function (data) {
                if (data.status === 'completed' && data.download_url) {
                    updateExportToast(toast, 'Export prêt. Téléchargement disponible.', 100);

                    var wrap = toast.querySelector('[data-export-download-wrap]');
                    var link = toast.querySelector('[data-export-download]');
                    if (wrap && link) {
                        wrap.classList.remove('hidden');
                        link.href = data.download_url;
                        link.setAttribute('download', data.filename || '');
                    }

                    forgetPendingExport(exportId);
                    triggerBackgroundDownload(data.download_url, data.filename || '');
                    return;
                }

                if (data.status === 'failed') {
                    updateExportToast(toast, data.error_message || 'Erreur lors de la génération de l’export.', 100);
                    forgetPendingExport(exportId);
                    return;
                }

                updateExportToast(
                    toast,
                    'Votre export est généré en arrière-plan... ' + (data.progress || 0) + '%',
                    data.progress || 0
                );

                window.setTimeout(function () {
                    pollExportStatus(exportId, exportType, statusUrl);
                }, 2000);
            })
            .catch(function (error) {
                updateExportToast(toast, error.message || 'Erreur lors de la génération de l’export.', 100);
            });
    }

    function resumePendingExports() {
        readPendingExports().forEach(function (item) {
            pollExportStatus(item.exportId, item.exportType, item.statusUrl);
        });
    }

    window.exportSelectedToExcel = function (exportType) {
        var ids = getSelectedTableIds(exportType);
        if (ids.length === 0) {
            alert('Veuillez sélectionner au moins un élément.');
            return;
        }

        var tableId = exportType;
        var bar = getBar(exportType);
        if (bar && bar.getAttribute('data-lm-table-id')) {
            tableId = bar.getAttribute('data-lm-table-id');
        }

        var columnsMode = 'all';
        var visibleColumns = [];
        if (window.LmTableColumns) {
            columnsMode = window.LmTableColumns.getExportMode(tableId) || 'all';
            if (columnsMode === 'visible') {
                visibleColumns = window.LmTableColumns.getVisibleKeys(tableId) || [];
            }
        }

        fetch(window.tableBulkExportUrl || '/export/table', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                type: exportType,
                ids: ids,
                columns_mode: columnsMode,
                visible_columns: visibleColumns
            })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Impossible de démarrer l’export.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                rememberPendingExport(data.export_id, exportType, data.status_url);
                var toast = ensureExportToast(data.export_id);
                updateExportToast(
                    toast,
                    data.message || 'Votre export est généré en arrière-plan.',
                    5
                );
                pollExportStatus(data.export_id, exportType, data.status_url);
            })
            .catch(function (error) {
                alert(error.message || 'Impossible de démarrer l’export.');
            });
    };

    window.deleteSelectedTable = function (exportType) {
        var ids = getSelectedTableIds(exportType);
        if (ids.length === 0) {
            alert('Veuillez sélectionner au moins un élément.');
            return;
        }

        if (!confirm('Supprimer ' + ids.length + ' élément(s) sélectionné(s) ? Cette action est irréversible.')) {
            return;
        }

        fetch(window.tableBulkDestroyUrl || '/export/table-destroy', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                type: exportType,
                ids: ids
            })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Impossible de supprimer la sélection.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                var lines = [data.message || 'Suppression terminée.'];
                if (data.blocked && data.blocked.length) {
                    lines.push('');
                    lines.push('Non supprimés :');
                    data.blocked.forEach(function (item) {
                        lines.push('- ' + (item.label || ('#' + item.id)) + ' : ' + item.reason);
                    });
                }
                alert(lines.join('\n'));
                if (data.deleted > 0) {
                    if (window.SoftNav && typeof window.SoftNav.navigate === 'function') {
                        window.SoftNav.navigate(window.location.href);
                    } else {
                        window.location.reload();
                    }
                }
            })
            .catch(function (error) {
                alert(error.message || 'Impossible de supprimer la sélection.');
            });
    };

    window.printSelectedTable = function (exportType) {
        var ids = getSelectedTableIds(exportType);
        if (ids.length === 0) {
            alert('Veuillez sélectionner au moins un élément.');
            return;
        }

        var bar = getBar(exportType);
        var pattern = bar ? bar.getAttribute('data-print-pattern') : null;
        if (!pattern) {
            alert('L’impression groupée n’est pas disponible pour cette liste.');
            return;
        }

        if (ids.length > 8 && !confirm('Ouvrir ' + ids.length + ' documents à imprimer ?')) {
            return;
        }

        ids.forEach(function (id) {
            window.open(pattern.replace('__ID__', String(id)), '_blank');
        });
    };

    var zipPdfTypes = ['invoices', 'quotes', 'purchase-orders', 'credit-notes', 'delivery-notes', 'supplier-invoices', 'supplier-delivery-notes', 'receptions', 'supplier-purchase-orders', 'supplier-credit-notes', 'expenses', 'expenses-with-invoice', 'expenses-without-invoice'];

    window.exportSelectedToZip = function (exportType) {
        if (zipPdfTypes.indexOf(exportType) === -1) {
            alert('Export ZIP PDF non disponible pour ce type de tableau.');
            return;
        }

        var ids = getSelectedTableIds(exportType);
        if (ids.length === 0) {
            alert('Veuillez sélectionner au moins un élément.');
            return;
        }

        fetch(window.tableBulkZipExportUrl || '/export/table-zip', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                type: exportType,
                ids: ids
            })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Impossible de démarrer l\'export ZIP.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                rememberPendingExport(data.export_id, exportType + '-zip', data.status_url);
                var toast = ensureExportToast(data.export_id);
                updateExportToast(
                    toast,
                    data.message || 'Votre export ZIP est généré en arrière-plan.',
                    5
                );
                pollExportStatus(data.export_id, exportType + '-zip', data.status_url);
            })
            .catch(function (error) {
                alert(error.message || 'Impossible de démarrer l\'export ZIP.');
            });
    };

    function handleCheckboxChange(target) {
        var exportType = target.getAttribute('data-export-type');
        if (!exportType) {
            return;
        }

        if (target.classList.contains('table-select-all')) {
            toggleTableSelectAll(target, exportType);
            return;
        }

        if (target.classList.contains('table-row-checkbox')) {
            highlightRow(target);
            updateTableSelectedCount(exportType);
        }
    }

    function initTableBulkSelection() {
        document.body.addEventListener('change', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox') {
                return;
            }
            if (
                target.classList.contains('table-row-checkbox') ||
                target.classList.contains('table-select-all')
            ) {
                handleCheckboxChange(target);
            }
        });

        document.body.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox') {
                return;
            }
            if (
                target.classList.contains('table-row-checkbox') ||
                target.classList.contains('table-select-all')
            ) {
                event.stopPropagation();
            }
        }, true);

        resumePendingExports();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTableBulkSelection);
    } else {
        initTableBulkSelection();
    }
})();
