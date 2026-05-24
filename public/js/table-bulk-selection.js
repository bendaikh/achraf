(function () {
    function getBar(exportType) {
        return document.getElementById('bulkActionsBar-' + exportType);
    }

    function getCheckboxes(exportType) {
        return document.querySelectorAll('.table-row-checkbox[data-export-type="' + exportType + '"]');
    }

    window.toggleTableSelectAll = function (checkbox, exportType) {
        getCheckboxes(exportType).forEach(function (cb) {
            cb.checked = checkbox.checked;
        });
        updateTableSelectedCount(exportType);
    };

    window.updateTableSelectedCount = function (exportType) {
        var checked = document.querySelectorAll('.table-row-checkbox[data-export-type="' + exportType + '"]:checked');
        var bar = getBar(exportType);
        var countEl = document.getElementById('selectedCount-' + exportType);
        var selectAll = document.getElementById('selectAll-' + exportType);

        if (!bar || !countEl) return;

        if (checked.length > 0) {
            bar.classList.remove('hidden');
            countEl.textContent = checked.length;
        } else {
            bar.classList.add('hidden');
        }

        if (selectAll) {
            var all = getCheckboxes(exportType);
            selectAll.checked = all.length > 0 && checked.length === all.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        }
    };

    window.clearTableSelection = function (exportType) {
        getCheckboxes(exportType).forEach(function (cb) {
            cb.checked = false;
        });
        var selectAll = document.getElementById('selectAll-' + exportType);
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        updateTableSelectedCount(exportType);
    };

    window.getSelectedTableIds = function (exportType) {
        return Array.from(
            document.querySelectorAll('.table-row-checkbox[data-export-type="' + exportType + '"]:checked')
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
})();
