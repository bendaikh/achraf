@extends('layouts.with-sidebar')

@section('title', 'Inventaire '.$warehouse->name)
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8 max-w-5xl">
        <h1 class="text-2xl font-bold text-slate-900 mb-1">Inventaire physique — {{ $warehouse->name }}</h1>
        <p class="text-sm text-slate-600 mb-6">Saisissez la quantité comptée (y compris <strong>0</strong>). L’écart génère un mouvement « Ajustement inventaire ». Sélectionnez des lignes, saisissez une quantité, puis cliquez <strong>Appliquer</strong> — enfin validez l’inventaire.</p>

        <form method="POST" action="{{ route('stock.locations.count.store', $warehouse) }}" id="inventory-count-form" class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            @csrf

            @if($slots->isNotEmpty())
                <div id="inventory-bulk-bar" class="hidden px-4 py-3 border-b border-slate-200 bg-[#0a5d8a]/5">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-medium text-slate-800">
                            <span id="inventory-selected-count">0</span> sélectionné(s)
                        </span>
                        <label class="text-sm text-slate-700 flex items-center gap-2">
                            <span>Set comptée</span>
                            <input type="number" min="0" step="1" id="inventory-bulk-qty" value="0" class="w-28 text-right rounded-lg border-slate-300 text-sm">
                        </label>
                        <button type="button" id="inventory-bulk-apply" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold hover:bg-[#074866]">
                            Appliquer
                        </button>
                        <button type="button" id="inventory-bulk-clear" class="px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 bg-white hover:bg-slate-50">
                            Tout désélectionner
                        </button>
                    </div>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm" id="inventory-count-table">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left w-12">
                                @if($slots->isNotEmpty())
                                    <input type="checkbox" id="inventory-select-all" class="h-4 w-4 rounded border-slate-300 text-[#0a5d8a] focus:ring-[#0a5d8a]" title="Tout sélectionner" aria-label="Tout sélectionner">
                                @endif
                            </th>
                            <th class="px-4 py-3 text-left">Produit</th>
                            <th class="px-4 py-3 text-left">SKU</th>
                            <th class="px-4 py-3 text-left">Emplacement</th>
                            <th class="px-4 py-3 text-right">Théorique</th>
                            <th class="px-4 py-3 text-right">Comptée</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($slots as $i => $slot)
                            <tr class="inventory-row hover:bg-slate-50/80" data-inventory-row>
                                <td class="px-4 py-3">
                                    <input type="checkbox" class="inventory-row-check h-4 w-4 rounded border-slate-300 text-[#0a5d8a] focus:ring-[#0a5d8a]" aria-label="Sélectionner {{ $slot->product?->name }}">
                                </td>
                                <td class="px-4 py-3">
                                    {{ $slot->product?->name }}
                                    @if($slot->variant)
                                        <div class="text-xs text-slate-500">{{ $slot->variant->title ?: $slot->variant->sku }}</div>
                                    @endif
                                    <input type="hidden" name="counts[{{ $i }}][product_id]" value="{{ $slot->product_id }}">
                                    <input type="hidden" name="counts[{{ $i }}][product_stock_id]" value="{{ $slot->id }}">
                                    <input type="hidden" name="counts[{{ $i }}][warehouse_location_id]" value="{{ $slot->warehouse_location_id }}">
                                    <input type="hidden" name="counts[{{ $i }}][product_variant_id]" value="{{ $slot->product_variant_id }}">
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $slot->product?->ref }}</td>
                                <td class="px-4 py-3 text-xs">{{ $slot->location?->displayLabel() ?: '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $slot->quantity }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input type="number" min="0" step="1" name="counts[{{ $i }}][counted]" value="{{ $slot->quantity }}" required class="inventory-counted-input w-24 text-right rounded-lg border-slate-300 text-sm">
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Aucun produit physique avec stock &gt; 0 dans ce dépôt.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($slots->isNotEmpty())
                <div class="p-4 border-t flex gap-3">
                    <button type="submit" class="px-5 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Valider l’inventaire</button>
                    <a href="{{ route('stock.locations.show', $warehouse) }}" class="px-5 py-2.5 border rounded-lg text-sm">Annuler</a>
                </div>
            @endif
        </form>
    </div>
</main>

@if($slots->isNotEmpty())
{{-- Inline in main so soft-nav keeps this script; SoftNav.whenReady waits until the table is mounted. --}}
<script>
(function () {
    function bootInventorySelection() {
        var form = document.getElementById('inventory-count-form');
        if (!form || form.dataset.inventoryBound === '1') return;
        form.dataset.inventoryBound = '1';

        var selectAll = document.getElementById('inventory-select-all');
        var bulkBar = document.getElementById('inventory-bulk-bar');
        var selectedCountEl = document.getElementById('inventory-selected-count');
        var bulkQty = document.getElementById('inventory-bulk-qty');
        var applyBtn = document.getElementById('inventory-bulk-apply');
        var clearBtn = document.getElementById('inventory-bulk-clear');

        function rowChecks() {
            return Array.prototype.slice.call(form.querySelectorAll('.inventory-row-check'));
        }

        function selectedChecks() {
            return rowChecks().filter(function (cb) { return cb.checked; });
        }

        function syncSelectionUi() {
            var checks = rowChecks();
            var selected = selectedChecks();
            var n = selected.length;

            if (selectedCountEl) selectedCountEl.textContent = String(n);
            if (bulkBar) {
                bulkBar.classList.toggle('hidden', n === 0);
            }

            if (selectAll) {
                selectAll.checked = checks.length > 0 && n === checks.length;
                selectAll.indeterminate = n > 0 && n < checks.length;
            }

            checks.forEach(function (cb) {
                var row = cb.closest('tr');
                if (row) {
                    if (cb.checked) {
                        row.classList.add('bg-[#0a5d8a]/5');
                    } else {
                        row.classList.remove('bg-[#0a5d8a]/5');
                    }
                }
            });
        }

        function applyBulkQty() {
            var selected = selectedChecks();
            if (!selected.length) return;

            if (!bulkQty || bulkQty.value === '') {
                if (bulkQty) bulkQty.focus();
                return;
            }

            var qty = parseInt(bulkQty.value, 10);
            if (isNaN(qty) || qty < 0) {
                bulkQty.focus();
                return;
            }

            selected.forEach(function (cb) {
                var row = cb.closest('tr');
                if (!row) return;
                var input = row.querySelector('.inventory-counted-input');
                if (input) input.value = String(qty);
            });

            if (bulkQty) bulkQty.focus();
        }

        if (selectAll) {
            selectAll.addEventListener('click', function (e) {
                e.stopPropagation();
            });
            selectAll.addEventListener('change', function () {
                var on = !!selectAll.checked;
                rowChecks().forEach(function (cb) { cb.checked = on; });
                syncSelectionUi();
            });
        }

        form.addEventListener('change', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('inventory-row-check')) {
                syncSelectionUi();
            }
        });

        form.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.classList) return;
            // Clicking the product cell toggles selection (except inputs).
            if (t.closest('input, button, a, label')) return;
            var row = t.closest('tr[data-inventory-row]');
            if (!row || !form.contains(row)) return;
            var cb = row.querySelector('.inventory-row-check');
            if (!cb) return;
            cb.checked = !cb.checked;
            syncSelectionUi();
        });

        if (applyBtn) {
            applyBtn.addEventListener('click', function (e) {
                e.preventDefault();
                applyBulkQty();
            });
        }

        if (bulkQty) {
            bulkQty.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyBulkQty();
                }
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                rowChecks().forEach(function (cb) { cb.checked = false; });
                if (selectAll) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }
                syncSelectionUi();
            });
        }

        syncSelectionUi();
    }

    if (window.SoftNav && typeof window.SoftNav.whenReady === 'function') {
        window.SoftNav.whenReady(bootInventorySelection);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootInventorySelection);
    } else {
        bootInventorySelection();
    }
})();
</script>
@endif
@endsection
