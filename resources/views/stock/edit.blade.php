<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajuster le stock — {{ $product->ref }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <aside class="w-64 bg-white shadow-lg flex flex-col relative">
            @include('layouts.sidebar')
        </aside>

        <main class="flex-1 overflow-y-auto">
            <div class="p-8 max-w-2xl">
                <div class="mb-6">
                    <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                        <a href="{{ route('stock.index') }}" class="hover:text-blue-600">Gestion stock</a>
                        <span>/</span>
                        <span class="text-gray-900">Ajuster</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">Ajuster le stock</h1>
                    <p class="text-gray-500 mt-1">{{ $product->ref }} — {{ $product->name }}</p>
                </div>

                @if($errors->any())
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <ul class="list-disc list-inside text-red-600 text-sm">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="bg-white rounded-lg shadow p-6 mb-6 border border-gray-100">
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500">Stock actuel</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $product->stock_quantity }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Seuils</dt>
                            <dd class="text-gray-900">Alerte : {{ $product->minimum_alert_stock ?? '—' }} · Sécurité : {{ $product->minimum_safety_stock ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <form action="{{ route('stock.update', $product) }}" method="POST" class="bg-white rounded-lg shadow p-6">
                    @csrf
                    @method('PATCH')
                    <div class="mb-6">
                        <label for="stock_quantity" class="block text-sm font-medium text-gray-700 mb-1">Nouvelle quantité en stock</label>
                        <input type="number" name="stock_quantity" id="stock_quantity" min="0" required
                            value="{{ old('stock_quantity', $product->stock_quantity) }}"
                            class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="mt-2 text-xs text-gray-500">Saisissez la quantité physique après inventaire ou réception.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg font-medium hover:from-blue-700 hover:to-purple-700">
                            Enregistrer
                        </button>
                        <a href="{{ route('stock.index') }}" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
