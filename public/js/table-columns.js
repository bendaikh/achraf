(function () {
    'use strict';

    var MOBILE_QUERY = window.matchMedia('(max-width: 767px)');
    var SAVE_DEBOUNCE_MS = 600;
    var instances = {};
    var saveTimers = {};

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function apiUrl(path) {
        var base = window.lmTableColumnsApi || {};
        if (path === 'show' && base.show) {
            return base.show.replace('__TABLE__', encodeURIComponent(arguments[1] || ''));
        }
        if (path === 'update' && base.update) {
            return base.update.replace('__TABLE__', encodeURIComponent(arguments[1] || ''));
        }
        if (path === 'reset' && base.reset) {
            return base.reset.replace('__TABLE__', encodeURIComponent(arguments[1] || ''));
        }
        if (path === 'defaults' && base.defaults) {
            return base.defaults.replace('__TABLE__', encodeURIComponent(arguments[1] || ''));
        }
        return '';
    }

    function readConfigScript(tableId) {
        var script = document.querySelector('script[data-lm-table-config="' + tableId + '"]');
        if (!script) {
            return null;
        }
        try {
            return JSON.parse(script.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function columnMeta(payload, key) {
        return (payload.columns || []).find(function (c) { return c.key === key; }) || { key: key, label: key };
    }

    function isLocked(payload, key) {
        var meta = columnMeta(payload, key);
        return !!meta.locked;
    }

    function isOptional(payload, key) {
        var meta = columnMeta(payload, key);
        return !!meta.optional;
    }

    function activeViewport(instance) {
        return instance.activeViewport || (MOBILE_QUERY.matches ? 'mobile' : 'desktop');
    }

    function configForViewport(instance, viewport) {
        viewport = viewport || activeViewport(instance);
        return instance.payload[viewport] || instance.payload.desktop;
    }

    function getTable(tableId) {
        return document.querySelector('[data-lm-table="' + tableId + '"]');
    }

    function getCells(table, key) {
        return table.querySelectorAll('.lm-col-' + key + ', .column-' + key);
    }

    function applyWidths(table, config) {
        Object.keys(config.widths || {}).forEach(function (key) {
            var width = config.widths[key];
            if (!width) {
                return;
            }
            getCells(table, key).forEach(function (cell) {
                cell.style.width = width + 'px';
                cell.style.minWidth = width + 'px';
                cell.style.maxWidth = width + 'px';
            });
        });
    }

    function applyVisibility(table, payload, config) {
        (payload.columns || []).forEach(function (col) {
            var visible = config.visible[col.key] !== false;
            getCells(table, col.key).forEach(function (cell) {
                cell.style.display = visible ? '' : 'none';
                cell.setAttribute('data-lm-col-hidden', visible ? 'false' : 'true');
            });
        });
    }

    function reorderColumns(table, order) {
        var theadRow = table.querySelector('thead tr');
        if (!theadRow) {
            return;
        }

        var bodyRows = table.querySelectorAll('tbody tr');

        order.forEach(function (key) {
            var headerCells = getCells(theadRow, key);
            headerCells.forEach(function (cell) {
                theadRow.appendChild(cell);
            });
        });

        bodyRows.forEach(function (row) {
            order.forEach(function (key) {
                getCells(row, key).forEach(function (cell) {
                    row.appendChild(cell);
                });
            });
        });
    }

    function applyConfig(instance, viewport) {
        var table = getTable(instance.tableId);
        if (!table) {
            return;
        }
        var config = configForViewport(instance, viewport);
        reorderColumns(table, config.order);
        applyVisibility(table, instance.payload, config);
        applyWidths(table, config);
        table.setAttribute('data-lm-viewport', viewport || activeViewport(instance));
    }

    function renderPickerList(instance) {
        var list = instance.picker.querySelector('[data-sortable-list]');
        if (!list) {
            return;
        }

        var viewport = instance.activeViewport || activeViewport(instance);
        var config = configForViewport(instance, viewport);
        list.innerHTML = '';

        config.order.forEach(function (key) {
            var meta = columnMeta(instance.payload, key);
            if (meta.key === 'select' && !document.querySelector('[data-lm-table="' + instance.tableId + '"] .lm-col-select, [data-lm-table="' + instance.tableId + '"] .table-select-all')) {
                return;
            }

            var item = document.createElement('div');
            item.className = 'lm-col-picker-item flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-gray-50';
            item.setAttribute('data-col-key', key);
            item.setAttribute('draggable', meta.locked ? 'false' : 'true');

            var handle = document.createElement('span');
            handle.className = 'lm-col-drag-handle text-gray-400 cursor-grab select-none text-sm';
            handle.textContent = meta.locked ? '🔒' : '☰';
            handle.setAttribute('aria-hidden', 'true');

            var labelWrap = document.createElement('label');
            labelWrap.className = 'flex items-center gap-2 flex-1 cursor-pointer min-w-0';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'lm-col-toggle rounded text-[#0a5d8a] focus:ring-[#0a5d8a]';
            checkbox.checked = config.visible[key] !== false;
            checkbox.disabled = !!meta.locked;
            checkbox.setAttribute('data-col-key', key);

            var label = document.createElement('span');
            label.className = 'text-sm text-gray-700 truncate';
            label.textContent = meta.label;

            labelWrap.appendChild(checkbox);
            labelWrap.appendChild(label);
            item.appendChild(handle);
            item.appendChild(labelWrap);
            list.appendChild(item);
        });

        bindPickerItemEvents(instance, list);
    }

    function bindPickerItemEvents(instance, list) {
        list.querySelectorAll('.lm-col-toggle').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                var key = checkbox.getAttribute('data-col-key');
                var viewport = instance.activeViewport || activeViewport(instance);
                var config = configForViewport(instance, viewport);
                config.visible[key] = checkbox.checked;
                instance.payload[viewport] = config;
                applyConfig(instance, viewport);
                scheduleSave(instance, viewport);
            });
        });

        var dragKey = null;
        list.querySelectorAll('.lm-col-picker-item[draggable="true"]').forEach(function (item) {
            item.addEventListener('dragstart', function (e) {
                dragKey = item.getAttribute('data-col-key');
                item.classList.add('opacity-50');
                e.dataTransfer.effectAllowed = 'move';
            });
            item.addEventListener('dragend', function () {
                item.classList.remove('opacity-50');
                dragKey = null;
            });
            item.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            item.addEventListener('drop', function (e) {
                e.preventDefault();
                var targetKey = item.getAttribute('data-col-key');
                if (!dragKey || dragKey === targetKey) {
                    return;
                }
                var viewport = instance.activeViewport || activeViewport(instance);
                var config = configForViewport(instance, viewport);
                var order = config.order.slice();
                var from = order.indexOf(dragKey);
                var to = order.indexOf(targetKey);
                if (from === -1 || to === -1) {
                    return;
                }
                order.splice(from, 1);
                order.splice(to, 0, dragKey);
                config.order = order;
                instance.payload[viewport] = config;
                renderPickerList(instance);
                applyConfig(instance, viewport);
                scheduleSave(instance, viewport);
            });
        });
    }

    function scheduleSave(instance, viewport) {
        var key = instance.tableId + ':' + viewport;
        window.clearTimeout(saveTimers[key]);
        saveTimers[key] = window.setTimeout(function () {
            persistConfig(instance, viewport);
        }, SAVE_DEBOUNCE_MS);
    }

    function persistConfig(instance, viewport, asDefault) {
        var config = configForViewport(instance, viewport);
        var url = asDefault ? apiUrl('defaults', instance.tableId) : apiUrl('update', instance.tableId);
        if (!url) {
            try {
                localStorage.setItem('lm-table-cols-' + instance.tableId + '-' + viewport, JSON.stringify(config));
            } catch (e) {}
            return;
        }

        fetch(url, {
            method: asDefault ? 'PUT' : 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ viewport: viewport, config: config }),
            credentials: 'same-origin'
        }).catch(function () {
            try {
                localStorage.setItem('lm-table-cols-' + instance.tableId + '-' + viewport, JSON.stringify(config));
            } catch (e) {}
        });
    }

    function resetConfig(instance, viewport) {
        var url = apiUrl('reset', instance.tableId);
        if (!url) {
            return;
        }
        fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ viewport: viewport }),
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                instance.payload[viewport] = data.config;
                renderPickerList(instance);
                applyConfig(instance, viewport);
            })
            .catch(function () {});
    }

    function bindPickerActions(instance) {
        var picker = instance.picker;

        picker.querySelector('.lm-col-show-all')?.addEventListener('click', function () {
            var viewport = instance.activeViewport || activeViewport(instance);
            var config = configForViewport(instance, viewport);
            (instance.payload.columns || []).forEach(function (col) {
                config.visible[col.key] = true;
            });
            instance.payload[viewport] = config;
            renderPickerList(instance);
            applyConfig(instance, viewport);
            scheduleSave(instance, viewport);
        });

        picker.querySelector('.lm-col-hide-optional')?.addEventListener('click', function () {
            var viewport = instance.activeViewport || activeViewport(instance);
            var config = configForViewport(instance, viewport);
            (instance.payload.columns || []).forEach(function (col) {
                if (isOptional(instance.payload, col.key) && !isLocked(instance.payload, col.key)) {
                    config.visible[col.key] = false;
                }
            });
            instance.payload[viewport] = config;
            renderPickerList(instance);
            applyConfig(instance, viewport);
            scheduleSave(instance, viewport);
        });

        picker.querySelector('.lm-col-reset')?.addEventListener('click', function () {
            resetConfig(instance, instance.activeViewport || activeViewport(instance));
        });

        picker.querySelector('.lm-col-save-default')?.addEventListener('click', function () {
            persistConfig(instance, instance.activeViewport || activeViewport(instance), true);
        });

        picker.querySelectorAll('.lm-col-viewport-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                instance.activeViewport = tab.getAttribute('data-viewport');
                picker.querySelectorAll('.lm-col-viewport-tab').forEach(function (t) {
                    var active = t === tab;
                    t.classList.toggle('bg-[#0a5d8a]', active);
                    t.classList.toggle('text-white', active);
                    t.classList.toggle('text-gray-600', !active);
                    t.classList.toggle('hover:bg-gray-100', !active);
                });
                renderPickerList(instance);
                applyConfig(instance, instance.activeViewport);
            });
        });
    }

    function bindTogglePanel(instance) {
        var btn = instance.picker.querySelector('.lm-col-picker-btn');
        var panel = instance.picker.querySelector('.lm-col-picker-panel');
        if (!btn || !panel) {
            return;
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = !panel.classList.contains('hidden');
            document.querySelectorAll('.lm-col-picker-panel').forEach(function (p) {
                p.classList.add('hidden');
            });
            if (!open) {
                panel.classList.remove('hidden');
                btn.setAttribute('aria-expanded', 'true');
                renderPickerList(instance);
            } else {
                btn.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('click', function (e) {
            if (!instance.picker.contains(e.target)) {
                panel.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function initPicker(tableId) {
        if (instances[tableId]) {
            applyConfig(instances[tableId]);
            return instances[tableId];
        }

        var picker = document.querySelector('[data-lm-column-picker="' + tableId + '"]');
        var payload = readConfigScript(tableId);
        var table = getTable(tableId);

        if (!picker || !payload || !table) {
            return null;
        }

        var instance = {
            tableId: tableId,
            picker: picker,
            payload: payload,
            activeViewport: MOBILE_QUERY.matches ? 'mobile' : 'desktop'
        };

        instances[tableId] = instance;
        bindTogglePanel(instance);
        bindPickerActions(instance);
        applyConfig(instance);

        MOBILE_QUERY.addEventListener('change', function () {
            instance.activeViewport = MOBILE_QUERY.matches ? 'mobile' : 'desktop';
            applyConfig(instance);
        });

        return instance;
    }

    function initAll() {
        document.querySelectorAll('[data-lm-column-picker]').forEach(function (el) {
            var tableId = el.getAttribute('data-lm-column-picker');
            if (tableId) {
                initPicker(tableId);
            }
        });
    }

    window.LmTableColumns = {
        init: initAll,
        initTable: initPicker,
        getVisibleKeys: function (tableId) {
            var instance = instances[tableId];
            if (!instance) {
                return [];
            }
            var config = configForViewport(instance);
            return (config.order || []).filter(function (key) {
                return config.visible[key] !== false;
            });
        },
        getExportMode: function (tableId) {
            var select = document.querySelector('[data-lm-export-mode="' + tableId + '"]');
            return select ? select.value : 'visible';
        }
    };

    function boot() {
        initAll();
    }

    if (window.SoftNav && typeof window.SoftNav.whenReady === 'function') {
        window.SoftNav.whenReady(boot);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.addEventListener('soft-nav:loaded', boot);
})();
