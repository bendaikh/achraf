@props([
    'tableId',
    'title',
    'itemLabel',
    'route',
    'targets',
])

<div id="salesConvertModal-{{ $tableId }}" data-convert-route="{{ $route }}" data-item-label="{{ $itemLabel }}" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="absolute inset-0 bg-gray-900/50" onclick="closeSalesConvertModal('{{ $tableId }}')"></div>
    <div class="relative mx-auto my-10 w-full max-w-2xl bg-white rounded-xl shadow-xl border border-gray-200 p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
                <p class="text-sm text-gray-500 mt-1">Choisissez le document à créer, puis le mode de regroupement.</p>
            </div>
            <button type="button" onclick="closeSalesConvertModal('{{ $tableId }}')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div id="salesConvertError-{{ $tableId }}" class="hidden mt-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3"></div>

        <div class="mt-6 space-y-5">
            @foreach($targets as $target)
                <section class="rounded-xl border {{ $target['border'] }} {{ $target['bg'] }} p-4">
                    <div class="mb-3">
                        <h4 class="font-semibold {{ $target['titleClass'] }}">{{ $target['heading'] }}</h4>
                        <p class="text-sm {{ $target['textClass'] }} mt-0.5">{{ $target['description'] }}</p>
                    </div>
                    <div class="space-y-3">
                        <button type="button" onclick="convertSelectedSalesDocuments('{{ $tableId }}', '{{ $target['key'] }}', 'separate')" class="w-full text-left p-4 bg-white border {{ $target['border'] }} rounded-lg hover:bg-white/80 transition">
                            <span class="block font-medium text-gray-900">{{ $target['separateLabel'] }}</span>
                            <span class="block text-sm text-gray-500 mt-1">{{ $target['separateHint'] }}</span>
                        </button>
                        <button type="button" onclick="convertSelectedSalesDocuments('{{ $tableId }}', '{{ $target['key'] }}', 'combined')" class="w-full text-left p-4 bg-white border {{ $target['border'] }} rounded-lg hover:bg-white/80 transition">
                            <span class="block font-medium text-gray-900">{{ $target['combinedLabel'] }}</span>
                            <span class="block text-sm text-gray-500 mt-1">{{ $target['combinedHint'] }}</span>
                        </button>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function openSalesConvertModal(tableId) {
    const modal = document.getElementById('salesConvertModal-' + tableId);
    const ids = window.getSelectedTableIds ? window.getSelectedTableIds(tableId) : [];
    if (ids.length === 0) {
        alert('Veuillez sélectionner au moins un ' + (modal?.dataset.itemLabel || 'document') + '.');
        return;
    }
    const error = document.getElementById('salesConvertError-' + tableId);
    if (error) {
        error.classList.add('hidden');
    }
    modal.classList.remove('hidden');
}

function closeSalesConvertModal(tableId) {
    document.getElementById('salesConvertModal-' + tableId).classList.add('hidden');
}

function convertSelectedSalesDocuments(tableId, target, mode) {
    const ids = window.getSelectedTableIds ? window.getSelectedTableIds(tableId) : [];
    const csrf = document.querySelector('meta[name="csrf-token"]');
    const modal = document.getElementById('salesConvertModal-' + tableId);
    const route = modal ? modal.dataset.convertRoute : null;
    const error = document.getElementById('salesConvertError-' + tableId);

    fetch(route, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
        },
        body: JSON.stringify({ ids, mode, target })
    })
        .then(response => response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || 'Erreur lors de la conversion.');
            }
            return data;
        }))
        .then(data => {
            window.location.href = data.redirect_url || window.location.href;
        })
        .catch(err => {
            if (error) {
                error.textContent = err.message || 'Erreur lors de la conversion.';
                error.classList.remove('hidden');
            }
        });
}
</script>
@endpush
@endonce
