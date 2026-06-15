@props(['suppliers' => [], 'selectedId' => null, 'required' => true])

<div class="party-select-field w-full min-w-0" x-data="{ showSupplierModal: false, supplierError: '' }">
    <div class="flex items-center gap-2 w-full min-w-0">
        <div class="party-select-wrap min-w-0 flex-1">
            <select
                name="supplier_id"
                id="supplier_id"
                @if($required) required @endif
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
                <option value="">Sélectionner un fournisseur</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected($selectedId == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <button
            type="button"
            @click="showSupplierModal = true; supplierError = ''"
            class="inline-flex items-center justify-center h-[38px] w-10 shrink-0 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150"
            title="Ajouter un fournisseur"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
        </button>
    </div>

    <template x-teleport="body">
        <div
            x-show="showSupplierModal"
            x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="showSupplierModal = false"
        >
            <div
                class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto border border-gray-200"
                @click.outside="showSupplierModal = false"
            >
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50 rounded-t-xl sticky top-0 z-10">
                    <h3 class="text-lg font-semibold text-gray-900">Nouveau fournisseur</h3>
                    <button type="button" @click="showSupplierModal = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form
                    class="p-6"
                    @submit.prevent="
                        supplierError = '';
                        const fd = new FormData($event.target);
                        fetch(@js(route('suppliers.quick-store')), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                            body: fd
                        }).then(r => r.json().then(d => ({ ok: r.ok, d }))).then(({ ok, d }) => {
                            if (!ok) { supplierError = d.message || (d.errors ? Object.values(d.errors).flat().join(' ') : 'Erreur lors de la création'); return; }
                            const sel = document.getElementById('supplier_id');
                            const opt = new Option(d.text, d.id, true, true);
                            sel.add(opt);
                            sel.value = d.id;
                            sel.dispatchEvent(new Event('change'));
                            showSupplierModal = false;
                            $event.target.reset();
                        }).catch(() => supplierError = 'Erreur réseau');
                    "
                >
                    <x-supplier-form-fields id-prefix="quick_supplier_" />

                    <p x-show="supplierError" x-text="supplierError" class="text-sm text-red-600 mt-4"></p>
                    <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-gray-200">
                        <button type="button" @click="showSupplierModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">Annuler</button>
                        <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 transition font-medium">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
