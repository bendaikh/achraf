<div x-data="{ open: {{ $errors->has('file') || session('import_errors') ? 'true' : 'false' }} }" class="mb-6">
    <div class="flex items-center gap-3">
        <button
            type="button"
            @click="open = !open"
            class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition duration-150 flex items-center gap-2"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
            </svg>
            Importer Excel
        </button>
        <a href="{{ $templateRoute }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
            Télécharger le modèle
        </a>
    </div>

    <div x-show="open" x-cloak class="mt-4 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Import Excel — {{ $label }}</h3>
        <p class="text-sm text-gray-600 mb-4">
            Téléchargez le modèle Excel, remplissez vos données (une ligne par article, regroupées par <code class="bg-gray-100 px-1 rounded">reference_import</code>), puis importez le fichier.
        </p>

        <form action="{{ $importRoute }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fichier Excel (.xlsx, .xls, .csv)</label>
                <input
                    type="file"
                    name="file"
                    accept=".xlsx,.xls,.csv"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                >
                @error('file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition duration-150">
                    Lancer l'import
                </button>
                <button type="button" @click="open = false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                    Fermer
                </button>
            </div>
        </form>

        @if(session('import_errors'))
            <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-sm font-medium text-red-800 mb-2">Erreurs d'import :</p>
                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                    @foreach(session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
