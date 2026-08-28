@extends('layouts.with-sidebar')

@section('title', 'Export documents comptable')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4">
            <h2 class="text-2xl font-bold text-gray-900">Export documents pour le comptable</h2>
            <p class="text-sm text-gray-600 mt-1">Sélectionnez une période, contrôlez les pièces manquantes, puis exportez Excel, ZIP ou PDF fusionné.</p>
        </div>
    </header>

    <div class="p-8 space-y-6">
        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg"><p class="text-sm text-green-700">{{ session('success') }}</p></div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg"><p class="text-sm text-red-700">{{ session('error') }}</p></div>
        @endif

        @php
            $inspection = $inspection ?? session('inspection');
            $selectedSections = old('sections', $defaults['sections'] ?? []);
        @endphp

        <form method="GET" action="{{ route('documents.archive.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
            <input type="hidden" name="preview" value="1">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                    <input type="date" name="date_from" value="{{ old('date_from', $defaults['date_from']) }}" required class="w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                    <input type="date" name="date_to" value="{{ old('date_to', $defaults['date_to']) }}" required class="w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Catégories de documents</p>
                <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($sections as $section)
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <input type="checkbox" name="sections[]" value="{{ $section['key'] }}" class="rounded border-gray-300"
                                @checked(in_array($section['key'], $selectedSections, true))>
                            <span>{{ $section['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-[#0a5d8a] px-4 py-2 text-sm font-medium text-white hover:bg-[#084a6e]">
                    1. Contrôler les documents
                </button>
            </div>
        </form>

        @if($inspection)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs uppercase text-gray-500">Documents attendus</p>
                        <p class="text-2xl font-bold">{{ $inspection['expected_count'] }}</p>
                    </div>
                    <div class="rounded-lg bg-green-50 p-4">
                        <p class="text-xs uppercase text-green-700">Documents présents</p>
                        <p class="text-2xl font-bold text-green-700">{{ $inspection['present_count'] }}</p>
                    </div>
                    <div class="rounded-lg bg-red-50 p-4">
                        <p class="text-xs uppercase text-red-700">Documents manquants</p>
                        <p class="text-2xl font-bold text-red-700">{{ $inspection['missing_count'] }}</p>
                    </div>
                </div>

                @if($inspection['missing_count'] > 0)
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                        <p class="font-medium text-red-800 mb-2">⚠️ {{ $inspection['missing_count'] }} document(s) manquant(s)</p>
                        <ul class="space-y-1 text-sm text-red-700">
                            @foreach($inspection['missing'] as $missing)
                                <li>
                                    {{ $missing['reference'] }} – {{ $missing['document_date_display'] }} – Document manquant
                                    @if(! empty($missing['attach_url']))
                                        <a href="{{ $missing['attach_url'] }}" class="ml-2 text-blue-700 underline">📎 Ajouter / Scanner le document</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('documents.archive.export') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="date_from" value="{{ $defaults['date_from'] }}">
                    <input type="hidden" name="date_to" value="{{ $defaults['date_to'] }}">
                    @foreach($selectedSections as $sectionKey)
                        <input type="hidden" name="sections[]" value="{{ $sectionKey }}">
                    @endforeach

                    @if($inspection['missing_count'] > 0)
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="{{ route('documents.archive.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">Annuler l’export</a>
                            <button type="submit" name="format" value="excel" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">Exporter → Excel</button>
                            <button type="submit" name="format" value="zip" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100" onclick="this.form.allow_missing.value=1">Continuer ZIP malgré les pièces manquantes</button>
                            <button type="submit" name="format" value="pdf" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700" onclick="this.form.allow_missing.value=1">Continuer PDF fusionné malgré les pièces manquantes</button>
                            <input type="hidden" name="allow_missing" value="0">
                        </div>
                    @else
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" name="format" value="excel" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">Exporter → Excel</button>
                        <button type="submit" name="format" value="zip" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">Exporter → Documents ZIP</button>
                        <button type="submit" name="format" value="pdf" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Exporter → PDF fusionné</button>
                    </div>
                    @endif
                    <p class="text-xs text-gray-500">Ordre garanti : Date du document ASC, puis Référence ASC. Les journées sans document sont ignorées.</p>
                </form>
            </div>
        @endif
    </div>
</main>
@endsection
