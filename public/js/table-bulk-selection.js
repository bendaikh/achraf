(function () {
    'use strict';

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

    function ensureExportToast(exportType) {
        var id = 'tableExportToast-' + exportType;
        var existing = document.getElementById(id);
        if (existing) {
            return existing;
        }

        var toast = document.createElement('div');
        toast.id = id;
        toast.className = 'table-export-toast bg-white border border-blue-200 rounded-xl shadow-lg p-4';
        toast.innerHTML = [
            '<div class="flex items-start justify-between gap-3">',
            '  <div>',
            '    <p class="text-sm font-semibold text-gray-900">Préparation de l’export</p>',
            '    <p class="text-xs text-gray-500 mt-1" data-export-message>Votre fichier est généré en arrière-plan.</p>',
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

        document.body.appendChild(toast);
        return toast;
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

    function pollExportStatus(exportType, statusUrl) {
        var toast = ensureExportToast(exportType);

        fetch(statusUrl, {
            headers: {
                Accept: 'application/json'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Impossible de suivre l’export.');
                }
                return response.json();
            })
            .then(function (data) {
                updateExportToast(
                    toast,
                    data.status === 'completed'
                        ? 'Export prêt. Vous pouvez le télécharger.'
                        : 'Export en cours... ' + (data.progress || 0) + '%',
                    data.progress || 0
                );

                if (data.status === 'completed' && data.download_url) {
                    var wrap = toast.querySelector('[data-export-download-wrap]');
                    var link = toast.querySelector('[data-export-download]');
                    if (wrap && link) {
                        wrap.classList.remove('hidden');
                        link.href = data.download_url;
                        link.setAttribute('download', data.filename || '');
                    }
                    window.location.href = data.download_url;
                    return;
                }

                if (data.status === 'failed') {
                    updateExportToast(toast, data.error_message || 'Erreur lors de la génération de l’export.', 100);
                    return;
                }

                window.setTimeout(function () {
                    pollExportStatus(exportType, statusUrl);
                }, 2000);
            })
            .catch(function (error) {
                updateExportToast(toast, error.message || 'Erreur lors de la génération de l’export.', 100);
            });
    }

    window.exportSelectedToExcel = function (exportType) {
        var ids = getSelectedTableIds(exportType);
        if (ids.length === 0) {
            alert('Veuillez sélectionner au moins un élément.');
            return;
        }

        var toast = ensureExportToast(exportType);
        updateExportToast(toast, 'Export demandé. Préparation du fichier...', 1);

        fetch(window.tableBulkExportUrl || '/export/table', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                type: exportType,
                ids: ids
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
                updateExportToast(toast, data.message || 'Export en cours de préparation.', 5);
                pollExportStatus(exportType, data.status_url);
            })
            .catch(function (error) {
                updateExportToast(toast, error.message || 'Impossible de démarrer l’export.', 100);
            });
    };

    var zipPdfTypes = ['invoices', 'quotes', 'purchase-orders', 'credit-notes', 'supplier-invoices'];

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

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = window.tableBulkZipExportUrl || '/export/table-zip';
        form.style.display = 'none';

        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf) {
            var tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = csrf.getAttribute('content');
            form.appendChild(tokenInput);
        }

        var typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'type';
        typeInput.value = exportType;
        form.appendChild(typeInput);

        ids.forEach(function (id) {
            var idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'ids[]';
            idInput.value = id;
            form.appendChild(idInput);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTableBulkSelection);
    } else {
        initTableBulkSelection();
    }
})();
