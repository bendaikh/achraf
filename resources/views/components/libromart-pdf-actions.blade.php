@props(['printRoute', 'pdfRoute'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1']) }} title="PDF généré par Libromart">
    <a href="{{ $printRoute }}?no_print=1" target="_blank" class="text-blue-600 hover:text-blue-900" title="Visualiser le PDF Libromart">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
        </svg>
    </a>
    <a href="{{ $pdfRoute }}" class="inline-flex items-center rounded bg-emerald-600 px-1.5 py-0.5 text-[11px] font-semibold text-white hover:bg-emerald-700" title="Télécharger le PDF Libromart">
        PDF
    </a>
    <a href="{{ $printRoute }}" target="_blank" class="text-green-600 hover:text-green-900" title="Imprimer le PDF Libromart">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
        </svg>
    </a>
</span>
