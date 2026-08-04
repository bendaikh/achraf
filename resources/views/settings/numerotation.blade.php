@extends('layouts.with-sidebar')

@section('title', 'Numérotation — Paramètres')
@section('sidebar_page_title', 'Paramètres')

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-6 lg:p-8 max-w-6xl mx-auto pb-16">
        @include('settings.partials.alerts')

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-start gap-3 px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-[#0a5d8a]/10 text-[#0a5d8a]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Numérotation des documents</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Formats, compteurs, réinitialisation et textes par défaut pour chaque type de document</p>
                </div>
            </div>
            <div class="p-6 space-y-3">
                @foreach ($documentSections as $doc)
                    @include('settings.partials.document-numbering', ['doc' => $doc])
                @endforeach
            </div>
        </div>
    </div>
</main>
@if(request('open'))
<script>
    document.getElementById(@json('settings-'.request('open')))?.scrollIntoView({ behavior: 'smooth', block: 'start' });
</script>
@endif
@endsection
