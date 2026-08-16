<div id="managed-scan-modal" class="hidden fixed inset-0 z-[80]">
    <div class="absolute inset-0 bg-black/40" data-scan-close></div>
    <div class="relative mx-auto mt-16 w-full max-w-3xl rounded-xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Scanner en PDF</h3>
                <p class="text-sm text-gray-500">Aperçu → Valider → Enregistrer</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600" data-scan-close>&times;</button>
        </div>
        <div class="space-y-4 px-6 py-5">
            <div id="managed-scan-status" class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800">
                Recherche d’un scanner connecté…
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 min-h-[220px] flex items-center justify-center">
                    <iframe id="managed-scan-preview" class="hidden h-56 w-full rounded border bg-white"></iframe>
                    <p id="managed-scan-placeholder" class="text-sm text-gray-500 text-center">Aucun aperçu pour le moment</p>
                </div>
                <div class="space-y-3 text-sm">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Scanner détecté</label>
                        <input id="managed-scan-device" type="text" readonly class="w-full rounded-lg border-gray-300 bg-gray-100" value="Non détecté">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Format</label>
                        <input type="text" readonly class="w-full rounded-lg border-gray-300 bg-gray-100" value="A4">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Mode</label>
                        <input type="text" readonly class="w-full rounded-lg border-gray-300 bg-gray-100" value="Noir et blanc / Couleur selon le scanner">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Importer le PDF du scanner</label>
                        <input id="managed-scan-file" type="file" accept="application/pdf,.pdf,image/*" class="block w-full text-sm">
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 border-t px-6 py-4">
            <button type="button" class="rounded-lg border px-4 py-2 text-sm" data-scan-close>Annuler</button>
            <button type="button" id="managed-scan-save" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" disabled>
                Enregistrer le document
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('managed-scan-modal');
    if (!modal || modal.dataset.bound === '1') return;
    modal.dataset.bound = '1';

    const statusEl = document.getElementById('managed-scan-status');
    const deviceEl = document.getElementById('managed-scan-device');
    const fileEl = document.getElementById('managed-scan-file');
    const preview = document.getElementById('managed-scan-preview');
    const placeholder = document.getElementById('managed-scan-placeholder');
    const saveBtn = document.getElementById('managed-scan-save');
    let current = null;
    let selectedFile = null;
    let previewUrl = null;

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function openModal(trigger) {
        current = {
            type: trigger.dataset.scanType,
            id: trigger.dataset.scanId,
            category: trigger.dataset.scanCategory || 'primary',
            url: trigger.dataset.scanUrl,
            bridge: trigger.dataset.scanBridge,
        };
        selectedFile = null;
        saveBtn.disabled = true;
        fileEl.value = '';
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
        deviceEl.value = 'Non détecté';
        statusEl.textContent = 'Recherche d’un scanner connecté…';
        statusEl.className = 'rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800';
        modal.classList.remove('hidden');
        tryBridge();
    }

    function closeModal() {
        modal.classList.add('hidden');
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
    }

    async function tryBridge() {
        if (!current?.bridge) {
            fallbackMessage();
            return;
        }
        try {
            const response = await fetch(current.bridge, {
                method: 'POST',
                headers: { 'Accept': 'application/pdf, application/json' },
                body: JSON.stringify({ format: 'A4', mode: 'color' }),
            });
            if (!response.ok) throw new Error('bridge unavailable');
            const blob = await response.blob();
            const file = new File([blob], 'scan.pdf', { type: blob.type || 'application/pdf' });
            setPreview(file);
            deviceEl.value = 'Scanner local (pont détecté)';
            statusEl.textContent = 'Scan reçu depuis le pont local. Vérifiez l’aperçu puis enregistrez.';
            statusEl.className = 'rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800';
        } catch (e) {
            fallbackMessage();
        }
    }

    function fallbackMessage() {
        statusEl.textContent = 'Aucun pont scanner navigateur détecté. Utilisez le logiciel de votre scanner pour produire un PDF, puis importez-le ici. Un pont local configurable (MANAGED_DOCUMENT_SCANNER_BRIDGE_URL) pourra lancer le scan automatiquement.';
        statusEl.className = 'rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800';
    }

    function setPreview(file) {
        selectedFile = file;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        saveBtn.disabled = false;
    }

    async function saveScan() {
        if (!selectedFile || !current) return;
        saveBtn.disabled = true;
        saveBtn.textContent = 'Enregistrement…';
        const form = new FormData();
        form.append('document_file', selectedFile, selectedFile.name || 'scan.pdf');
        form.append('category', current.category);
        form.append('source', 'scan');
        form.append('_token', csrfToken());
        try {
            const response = await fetch(current.url, {
                method: 'POST',
                body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                redirect: 'follow',
            });
            if (!response.ok) throw new Error('save failed');
            window.location.reload();
        } catch (e) {
            statusEl.textContent = 'Échec de l’enregistrement du scan. Réessayez.';
            statusEl.className = 'rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800';
            saveBtn.disabled = false;
            saveBtn.textContent = 'Enregistrer le document';
        }
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-managed-scan]');
        if (trigger) {
            event.preventDefault();
            openModal(trigger);
            return;
        }
        if (event.target.closest('[data-scan-close]')) {
            closeModal();
        }
    });

    fileEl?.addEventListener('change', function () {
        const file = fileEl.files && fileEl.files[0];
        if (file) setPreview(file);
    });
    saveBtn?.addEventListener('click', saveScan);
})();
</script>
