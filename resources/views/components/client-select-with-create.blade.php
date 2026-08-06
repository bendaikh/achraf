@props([
    'selectedId' => null,
    'selectedLabel' => null,
    'required' => true,
    'selectId' => 'client_id',
    'name' => 'client_id',
])

<div
    class="party-select-field w-full min-w-0"
    data-quick-store-url="{{ route('clients.quick-store') }}"
    data-select-id="{{ $selectId }}"
    x-data="{
        showClientModal: false,
        clientError: '',
        clientType: 'entreprise',
        quickStoreUrl: $el.dataset.quickStoreUrl,
        selectId: $el.dataset.selectId
    }"
>
    <div class="flex items-center gap-2 w-full min-w-0">
        <div class="party-select-wrap min-w-0 flex-1">
            <select
                @if($name !== null && $name !== '') name="{{ $name }}" @endif
                id="{{ $selectId }}"
                @if($required) required @endif
                class="w-full"
            >
                <option value="">Sélectionner un client</option>
                @if($selectedId && $selectedLabel)
                    <option value="{{ $selectedId }}" selected>{{ $selectedLabel }}</option>
                @endif
            </select>
        </div>
        <button
            type="button"
            @click="showClientModal = true; clientError = ''; clientType = 'entreprise'"
            class="inline-flex items-center justify-center h-[38px] w-10 shrink-0 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150"
            title="Ajouter un client"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
        </button>
    </div>

    <template x-teleport="body">
        <div
            x-show="showClientModal"
            x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="showClientModal = false"
        >
            <div
                class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto border border-gray-200"
                @click.outside="showClientModal = false"
            >
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50 rounded-t-xl sticky top-0 z-10">
                    <h3 class="text-lg font-semibold text-gray-900">Nouveau client</h3>
                    <button type="button" @click="showClientModal = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form
                    class="p-6"
                    @submit.prevent="
                        clientError = '';
                        const fd = new FormData($event.target);
                        fetch(quickStoreUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                            body: fd
                        }).then(r => r.json().then(d => ({ ok: r.ok, d }))).then(({ ok, d }) => {
                            if (!ok) { clientError = d.message || (d.errors ? Object.values(d.errors).flat().join(' ') : 'Erreur lors de la création'); return; }
                            const sel = document.getElementById(selectId);
                            const opt = new Option(d.text, d.id, true, true);
                            $(sel).append(opt).trigger('change');
                            showClientModal = false;
                            clientType = 'entreprise';
                            $event.target.reset();
                        }).catch(() => clientError = 'Erreur réseau');
                    "
                >
                    <div class="space-y-4">
                        <x-client-form-fields id-prefix="quick_client_" :compact="true" />
                    </div>

                    <p x-show="clientError" x-text="clientError" class="text-sm text-red-600 mt-4"></p>
                    <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-gray-200">
                        <button type="button" @click="showClientModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">Annuler</button>
                        <button type="submit" class="px-6 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] transition font-medium">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>

<script>
(function () {
    var selectId = @json($selectId);
    function init() {
        if (typeof window.initClientSelect2 !== 'function') return;
        if (!document.getElementById(selectId)) return;
        window.initClientSelect2('#' + selectId);
    }
    if (window.SoftNav && typeof SoftNav.whenReady === 'function') {
        SoftNav.whenReady(init);
    } else if (window.jQuery) {
        $(init);
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
</script>
