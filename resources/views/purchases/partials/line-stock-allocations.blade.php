@php
    $warehouses = $warehouses ?? collect();
    $warehousesPayload = $warehouses->map(fn ($w) => [
        'id' => $w->id,
        'name' => $w->name,
        'kind' => $w->kind,
        'is_online' => $w->isOnline(),
        'locations' => $w->locations->map(fn ($l) => [
            'id' => $l->id,
            'code' => $l->code,
            'name' => $l->name,
            'label' => $l->displayLabel(),
        ])->values(),
    ])->values();
@endphp
<script>
window.purchaseWarehouses = @json($warehousesPayload);

window.purchaseLineStockHtml = function (itemIndex) {
    var warehouses = window.purchaseWarehouses || [];
    var opts = warehouses.map(function (w) {
        var label = w.is_online ? ('🟢 ' + w.name) : w.name;
        return '<option value="' + w.id + '" data-online="' + (w.is_online ? '1' : '0') + '">' + label + '</option>';
    }).join('');
    return '' +
        '<div class="space-y-1 min-w-[11rem]" data-line-stock="' + itemIndex + '">' +
            '<select name="items[' + itemIndex + '][warehouse_id]" required class="w-full px-2 py-1 border border-gray-300 rounded text-sm purchase-line-warehouse" data-manual-override="0" onchange="window.purchaseLineWarehouseChanged(' + itemIndex + ')">' +
                '<option value="">Dépôt destination *</option>' + opts +
            '</select>' +
            '<select name="items[' + itemIndex + '][warehouse_location_id]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm purchase-line-location">' +
                '<option value="">Emplacement</option>' +
            '</select>' +
            '<button type="button" class="text-[10px] text-blue-700 hover:underline" onclick="window.purchaseToggleSplit(' + itemIndex + ')">Répartir sur plusieurs dépôts</button>' +
            '<div class="hidden space-y-1 border-t pt-1 mt-1 purchase-line-split" data-split="' + itemIndex + '">' +
                warehouses.map(function (w, wi) {
                    return '<label class="flex items-center gap-1 text-[10px] text-gray-600">' +
                        '<span class="w-24 truncate" title="' + w.name + '">' + w.name + '</span>' +
                        '<input type="hidden" name="items[' + itemIndex + '][allocations][' + wi + '][warehouse_id]" value="' + w.id + '">' +
                        '<input type="number" min="0" name="items[' + itemIndex + '][allocations][' + wi + '][quantity]" class="w-14 px-1 py-0.5 border rounded text-[10px]" placeholder="0">' +
                    '</label>';
                }).join('') +
            '</div>' +
        '</div>';
};

window.purchaseLineWarehouseChanged = function (itemIndex, options) {
    options = options || {};
    var root = document.querySelector('[data-line-stock="' + itemIndex + '"]');
    if (!root) return;
    var select = root.querySelector('.purchase-line-warehouse');
    var locSelect = root.querySelector('.purchase-line-location');
    if (!select || !locSelect) return;

    if (!options.fromHeader) {
        select.dataset.manualOverride = '1';
    }

    var previousLocationId = locSelect.value;
    var wid = parseInt(select.value || '0', 10);
    var warehouses = window.purchaseWarehouses || [];
    var warehouse = warehouses.find(function (w) { return w.id === wid; });
    locSelect.innerHTML = '<option value="">Emplacement</option>';
    if (!warehouse) return;

    var matched = false;
    (warehouse.locations || []).forEach(function (loc) {
        var opt = document.createElement('option');
        opt.value = loc.id;
        opt.textContent = loc.label || loc.code || loc.name;
        if (String(loc.id) === String(previousLocationId)) {
            opt.selected = true;
            matched = true;
        }
        locSelect.appendChild(opt);
    });

    // Emplacement doit toujours appartenir au dépôt de la ligne.
    if (!matched) {
        locSelect.value = '';
    }
};

window.purchaseSeedLineWarehouseFromHeader = function (itemIndex) {
    var header = document.querySelector('select[name="warehouse_id"]');
    var root = document.querySelector('[data-line-stock="' + itemIndex + '"]');
    if (!header || !header.value || !root) return;
    var lineWh = root.querySelector('.purchase-line-warehouse');
    if (!lineWh) return;
    lineWh.dataset.manualOverride = '0';
    lineWh.value = header.value;
    window.purchaseLineWarehouseChanged(itemIndex, { fromHeader: true });
};

window.purchaseApplyHeaderWarehouseToLines = function () {
    var header = document.querySelector('select[name="warehouse_id"]');
    if (!header || !header.value) return;

    document.querySelectorAll('[data-line-stock]').forEach(function (root) {
        var lineWh = root.querySelector('.purchase-line-warehouse');
        if (!lineWh) return;
        if (lineWh.dataset.manualOverride === '1') return;
        lineWh.value = header.value;
        var idx = parseInt(root.getAttribute('data-line-stock'), 10);
        if (!isNaN(idx)) {
            window.purchaseLineWarehouseChanged(idx, { fromHeader: true });
        }
    });
};

window.purchaseToggleSplit = function (itemIndex) {
    var el = document.querySelector('[data-split="' + itemIndex + '"]');
    if (el) el.classList.toggle('hidden');
};

document.addEventListener('DOMContentLoaded', function () {
    var header = document.querySelector('select[name="warehouse_id"]');
    if (!header || header.dataset.purchaseHeaderBound === '1') return;
    header.dataset.purchaseHeaderBound = '1';
    header.addEventListener('change', function () {
        window.purchaseApplyHeaderWarehouseToLines();
    });
});
</script>
