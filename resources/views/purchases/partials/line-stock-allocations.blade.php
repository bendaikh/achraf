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
            '<select name="items[' + itemIndex + '][warehouse_id]" required class="w-full px-2 py-1 border border-gray-300 rounded text-sm purchase-line-warehouse" onchange="window.purchaseLineWarehouseChanged(' + itemIndex + ')">' +
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
window.purchaseLineWarehouseChanged = function (itemIndex) {
    var root = document.querySelector('[data-line-stock="' + itemIndex + '"]');
    if (!root) return;
    var select = root.querySelector('.purchase-line-warehouse');
    var locSelect = root.querySelector('.purchase-line-location');
    var wid = parseInt(select.value || '0', 10);
    var warehouses = window.purchaseWarehouses || [];
    var warehouse = warehouses.find(function (w) { return w.id === wid; });
    locSelect.innerHTML = '<option value="">Emplacement</option>';
    if (!warehouse) return;
    (warehouse.locations || []).forEach(function (loc) {
        var opt = document.createElement('option');
        opt.value = loc.id;
        opt.textContent = loc.label || loc.code || loc.name;
        locSelect.appendChild(opt);
    });
};
window.purchaseToggleSplit = function (itemIndex) {
    var el = document.querySelector('[data-split="' + itemIndex + '"]');
    if (el) el.classList.toggle('hidden');
};
</script>
