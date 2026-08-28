@php
    $scanScript = public_path('js/mobile-document-scanner.js');
    $scanVersion = is_readable($scanScript) ? filemtime($scanScript) : time();
@endphp

<style>
    html.lm-is-desktop .lm-scan-mobile-only { display: none !important; }
    html.lm-is-mobile .lm-scan-desktop-only { display: none !important; }
    html.lm-is-mobile .lm-scan-mobile-only { display: flex !important; }
    body.lm-scanner-open { overflow: hidden; }
    #lm-scan-status[data-kind="error"] { background: #fef2f2; color: #991b1b; }
    #lm-scan-status[data-kind="ok"] { background: #ecfdf5; color: #065f46; }
    .lm-scan-shell { background: #0b1220; color: #fff; }
    .lm-scan-video-wrap { position: relative; background: #000; }
    .lm-scan-video-wrap video,
    .lm-scan-video-wrap canvas { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .lm-scan-shutter {
        width: 4.25rem; height: 4.25rem; border-radius: 9999px;
        border: 4px solid #fff; background: #2563eb; box-shadow: 0 0 0 6px rgba(37,99,235,.25);
    }
    .lm-scan-thumb {
        background: #111827; border: 1px solid #1f2937; border-radius: 0.85rem; overflow: hidden;
    }
    .lm-scan-thumb img { width: 100%; height: 9rem; object-fit: cover; display: block; }
    .lm-scan-thumb-meta { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.65rem; font-size: 0.75rem; }
    .lm-scan-thumb-actions { display: flex; gap: 0.35rem; }
    .lm-scan-thumb-actions button,
    .lm-scan-icon-btn {
        min-width: 2rem; min-height: 2rem; border-radius: 0.5rem; background: #1f2937; color: #fff;
    }
    .lm-scan-add-page {
        min-height: 9rem; border: 2px dashed #374151; border-radius: 0.85rem; color: #93c5fd; font-weight: 600;
    }
    .lm-scan-filter.is-active { background: #2563eb; color: #fff; border-color: #2563eb; }
    #lm-scan-crop-canvas, #lm-scan-enhance-canvas { touch-action: none; max-width: 100%; border-radius: 0.75rem; }
    #lm-mobile-scanner [data-scan-step] { display: none; flex-direction: column; flex: 1; min-height: 0; }
    #lm-mobile-scanner [data-scan-step]:not(.hidden) { display: flex; }
    #lm-mobile-scanner [data-scan-step].hidden { display: none !important; }
</style>

<div id="lm-mobile-scanner" class="hidden fixed inset-0 z-[120] lm-scan-shell">
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between px-4 py-3">
            <button type="button" class="text-sm text-gray-300" data-scan-close>Fermer</button>
            <h3 class="text-sm font-semibold">Scanner un document</h3>
            <span class="w-12"></span>
        </div>

        <p id="lm-scan-status" class="hidden mx-4 mb-2 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-900"></p>

        <div data-scan-step="capture" class="flex min-h-0 flex-1 flex-col">
            <div class="lm-scan-video-wrap relative mx-4 flex-1 overflow-hidden rounded-2xl">
                <video id="lm-scan-video" autoplay muted playsinline></video>
                <canvas id="lm-scan-live-overlay"></canvas>
            </div>
            <div class="flex items-center justify-between px-8 py-6">
                <button type="button" class="lm-scan-icon-btn px-3 text-xs" data-scan-gallery>Galerie</button>
                <button type="button" class="lm-scan-shutter" data-scan-shutter aria-label="Prendre la photo"></button>
                <span class="w-12"></span>
            </div>
        </div>

        <div data-scan-step="crop" class="hidden flex min-h-0 flex-1 flex-col">
            <div class="flex flex-1 items-center justify-center px-4">
                <canvas id="lm-scan-crop-canvas" class="max-h-full"></canvas>
            </div>
            <div class="grid grid-cols-4 gap-2 px-4 pb-3 text-center text-xs text-gray-300">
                <span>Recadrer</span>
                <button type="button" data-scan-rotate-capture>Pivoter</button>
                <button type="button" data-scan-retake>Refaire</button>
                <span>Coins</span>
            </div>
            <div class="px-4 pb-5">
                <button type="button" class="w-full rounded-xl bg-blue-600 py-3 text-sm font-semibold" data-scan-accept-crop>Valider</button>
            </div>
        </div>

        <div data-scan-step="pages" class="hidden flex min-h-0 flex-1 flex-col">
            <div id="lm-scan-pages-list" class="grid flex-1 grid-cols-2 content-start gap-3 overflow-auto px-4 pb-4"></div>
            <div class="px-4 pb-5">
                <button type="button" class="w-full rounded-xl bg-blue-600 py-3 text-sm font-semibold" data-scan-pages-next>Suivant</button>
            </div>
        </div>

        <div data-scan-step="enhance" class="hidden flex min-h-0 flex-1 flex-col">
            <div class="flex items-center justify-between px-4 text-sm">
                <button type="button" data-scan-enhance-prev>←</button>
                <span id="lm-scan-enhance-label">Page 1</span>
                <button type="button" data-scan-enhance-next>→</button>
            </div>
            <div class="flex flex-1 items-center justify-center px-4 py-3">
                <canvas id="lm-scan-enhance-canvas" class="max-h-full"></canvas>
            </div>
            <div class="grid grid-cols-4 gap-2 px-4 text-xs">
                <button type="button" class="lm-scan-filter rounded-lg border border-gray-600 py-2" data-scan-filter="original">Original</button>
                <button type="button" class="lm-scan-filter rounded-lg border border-gray-600 py-2" data-scan-filter="color">Couleur</button>
                <button type="button" class="lm-scan-filter rounded-lg border border-gray-600 py-2" data-scan-filter="gray">Niveaux de gris</button>
                <button type="button" class="lm-scan-filter rounded-lg border border-gray-600 py-2" data-scan-filter="bw">Noir & blanc</button>
            </div>
            <label class="mx-4 mt-3 mb-4 text-xs text-gray-300">
                Luminosité
                <input id="lm-scan-brightness" type="range" min="20" max="80" value="55" class="mt-1 w-full">
            </label>
            <div class="px-4 pb-5">
                <button type="button" class="w-full rounded-xl bg-blue-600 py-3 text-sm font-semibold" data-scan-save>Créer le PDF et enregistrer</button>
            </div>
        </div>

        <div data-scan-step="saving" class="hidden flex flex-1 flex-col items-center justify-center px-8 text-center">
            <div class="mb-4 h-12 w-12 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
            <p class="text-sm text-gray-200">Création du PDF et rattachement à l’élément…</p>
        </div>
    </div>
    <input id="lm-scan-gallery" type="file" accept="image/*" class="hidden">
</div>

<script src="{{ asset('js/mobile-document-scanner.js') }}?v={{ $scanVersion }}"></script>
