@php
    use App\Models\Warehouse;

    $warehouses = $warehouses ?? Warehouse::query()
        ->active()
        ->physical()
        ->orderByDesc('is_fulfillment_default')
        ->orderBy('name')
        ->get();

    $defaultWarehouse = $warehouses->firstWhere('is_fulfillment_default', true) ?? $warehouses->first();
    $warehousesPayload = $warehouses->map(fn ($w) => [
        'id' => $w->id,
        'name' => $w->name,
        'is_fulfillment_default' => (bool) $w->is_fulfillment_default,
    ])->values();
@endphp
<script>
window.salesWarehouses = @json($warehousesPayload);
window.salesDefaultWarehouseId = @json($defaultWarehouse?->id);
window.salesProductSlotsUrlTemplate = @json(url('/products/__ID__/location-stocks'));

window.salesLineStockHtml = function (itemIndex, data) {
    data = data || {};
    var warehouses = window.salesWarehouses || [];
    var defaultId = data.warehouse_id || window.salesDefaultWarehouseId || '';
    var opts = warehouses.map(function (w) {
        var selected = String(w.id) === String(defaultId) ? ' selected' : '';
        return '<option value="' + w.id + '"' + selected + '>' + w.name + '</option>';
    }).join('');

    return '' +
        '<div class="space-y-1 min-w-[12rem]" data-sales-line-stock="' + itemIndex + '">' +
            '<select name="items[' + itemIndex + '][warehouse_id]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm sales-line-warehouse" onchange="window.salesLineWarehouseChanged(' + itemIndex + ')">' +
                '<option value="">Dépôt source</option>' + opts +
            '</select>' +
            '<select name="items[' + itemIndex + '][warehouse_location_id]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm sales-line-location" data-selected="' + (data.warehouse_location_id || '') + '">' +
                '<option value="">Emplacement (stock)</option>' +
            '</select>' +
            '<p class="text-[10px] text-slate-500 sales-line-stock-hint">Stock dispo : —</p>' +
        '</div>';
};

window.salesLineWarehouseChanged = function (itemIndex) {
    window.salesRefreshLineLocations(itemIndex);
};

window.salesRefreshLineLocations = function (itemIndex, preferredLocationId) {
    var root = document.querySelector('[data-sales-line-stock="' + itemIndex + '"]');
    if (!root) return;

    var warehouseSelect = root.querySelector('.sales-line-warehouse');
    var locationSelect = root.querySelector('.sales-line-location');
    var hint = root.querySelector('.sales-line-stock-hint');
    if (!warehouseSelect || !locationSelect) return;

    var warehouseId = parseInt(warehouseSelect.value || '0', 10);
    var productSelect = document.getElementById('product_select_' + itemIndex);
    var productId = productSelect ? productSelect.value : '';
    var variantEl = document.getElementById('product_variant_id_' + itemIndex);
    var variantId = variantEl ? variantEl.value : '';
    var keepLocationId = preferredLocationId != null
        ? preferredLocationId
        : (locationSelect.dataset.selected || locationSelect.value || '');

    locationSelect.innerHTML = '<option value="">Emplacement (stock)</option>';
    if (hint) hint.textContent = 'Stock dispo : —';

    if (!warehouseId || !productId) {
        return;
    }

    var url = String(window.salesProductSlotsUrlTemplate || '').replace('__ID__', productId);
    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (payload) {
            if (!payload) return;
            var slots = (payload.physical_slots || []).filter(function (slot) {
                if (parseInt(slot.warehouse_id, 10) !== warehouseId) return false;
                if (!slot.has_location || !slot.warehouse_location_id) return false;
                var available = Math.max(0, (parseInt(slot.quantity, 10) || 0) - (parseInt(slot.reserved, 10) || 0));
                if (available <= 0 && (parseInt(slot.quantity, 10) || 0) <= 0) return false;
                if (variantId && slot.product_variant_id && String(slot.product_variant_id) !== String(variantId)) {
                    return false;
                }
                return true;
            });

            var matched = false;
            slots.forEach(function (slot) {
                var available = Math.max(0, (parseInt(slot.quantity, 10) || 0) - (parseInt(slot.reserved, 10) || 0));
                var opt = document.createElement('option');
                opt.value = slot.warehouse_location_id;
                opt.textContent = (slot.location_label || 'Emplacement') + ' (' + available + ')';
                opt.dataset.available = String(available);
                if (String(slot.warehouse_location_id) === String(keepLocationId)) {
                    opt.selected = true;
                    matched = true;
                    if (hint) hint.textContent = 'Stock dispo : ' + available;
                }
                locationSelect.appendChild(opt);
            });

            if (!matched && slots.length === 1) {
                locationSelect.value = String(slots[0].warehouse_location_id);
                var onlyAvailable = Math.max(0, (parseInt(slots[0].quantity, 10) || 0) - (parseInt(slots[0].reserved, 10) || 0));
                if (hint) hint.textContent = 'Stock dispo : ' + onlyAvailable;
            } else if (!matched && slots.length > 0 && !keepLocationId) {
                // Prefill first location with stock at Magasin Belvédère.
                locationSelect.value = String(slots[0].warehouse_location_id);
                var firstAvailable = Math.max(0, (parseInt(slots[0].quantity, 10) || 0) - (parseInt(slots[0].reserved, 10) || 0));
                if (hint) hint.textContent = 'Stock dispo : ' + firstAvailable;
            } else if (!slots.length && hint) {
                hint.textContent = 'Aucun emplacement avec stock';
            }

            locationSelect.dataset.selected = locationSelect.value || '';
        })
        .catch(function () {
            if (hint) hint.textContent = 'Stock dispo : —';
        });
};

window.salesSeedLineStock = function (itemIndex, data) {
    data = data || {};
    var root = document.querySelector('[data-sales-line-stock="' + itemIndex + '"]');
    if (!root) return;
    var warehouseSelect = root.querySelector('.sales-line-warehouse');
    var locationSelect = root.querySelector('.sales-line-location');
    if (warehouseSelect) {
        warehouseSelect.value = data.warehouse_id || window.salesDefaultWarehouseId || warehouseSelect.value || '';
    }
    if (locationSelect && data.warehouse_location_id) {
        locationSelect.dataset.selected = data.warehouse_location_id;
    }
    window.salesRefreshLineLocations(itemIndex, data.warehouse_location_id || null);
};

window.onCommercialProductFilled = function (index) {
    window.salesRefreshLineLocations(index);
};

document.addEventListener('change', function (event) {
    var target = event.target;
    if (!target || !target.classList || !target.classList.contains('sales-line-location')) {
        return;
    }
    var root = target.closest('[data-sales-line-stock]');
    if (!root) return;
    var hint = root.querySelector('.sales-line-stock-hint');
    var selected = target.options[target.selectedIndex];
    target.dataset.selected = target.value || '';
    if (hint) {
        hint.textContent = selected && selected.dataset.available
            ? ('Stock dispo : ' + selected.dataset.available)
            : 'Stock dispo : —';
    }
});
</script>
