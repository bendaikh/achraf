{{-- Shared header for « Gestion financière clients » pages. --}}
<header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
    <div class="px-4 sm:px-8 py-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]/70">Gestion financière clients</p>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">{{ $title }}</h2>
            <p class="text-sm text-gray-600 mt-1">{{ $subtitle }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions ?? '' }}
            <a href="{{ route('sales.payments.index') }}" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition text-sm font-medium">
                Gestion des paiements
            </a>
            <a href="{{ route('invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm">
                Factures clients (Ventes)
            </a>
        </div>
    </div>
</header>
