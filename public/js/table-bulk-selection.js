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

    window.exportSelectedToExcel = function (exportType) {
        var ids = getSelectedTableIds(exportType);
        if (ids.length === 0) {
            alert('Veuillez sélectionner au moins un élément.');
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = window.tableBulkExportUrl || '/export/table';
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
