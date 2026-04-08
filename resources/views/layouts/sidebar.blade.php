<div class="flex flex-col h-full">
    <div class="p-4 border-b border-gray-200 flex-shrink-0">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <div>
                <h1 class="text-lg font-bold text-gray-900">hsabati</h1>
                <p class="text-xs text-gray-500">Fast Tuning Car</p>
            </div>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto p-4 space-y-2">
    <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }} transition duration-150">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
        </svg>
        <span class="font-medium">Tableau de bord</span>
    </a>

    <div x-data="{ open: {{ request()->is('purchases/*') ? 'true' : 'false' }} }" class="space-y-1">
        <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition duration-150">
            <div class="flex items-center space-x-3">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                <span class="font-medium">Gestion achats</span>
            </div>
            <svg :class="{'rotate-180': open}" class="h-4 w-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div x-show="open" x-transition class="ml-4 space-y-1 border-l-2 border-gray-200 pl-4">
            <a href="{{ route('expenses.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('expenses.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span>Dépenses</span>
            </a>
            <a href="{{ route('supplier-purchase-orders.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('supplier-purchase-orders.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>BC Fournisseur</span>
            </a>
            <a href="{{ route('receptions.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('receptions.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span>Bon de réception</span>
            </a>
            <a href="{{ route('supplier-invoices.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('supplier-invoices.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Factures fournisseur</span>
            </a>
            <a href="{{ route('supplier-credit-notes.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('supplier-credit-notes.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                </svg>
                <span>Avoirs fournisseur</span>
            </a>
        </div>
    </div>

    <div x-data="{ open: {{ request()->routeIs('pos.*') ? 'true' : 'false' }} }" class="space-y-1">
        <button @click="open = !open" type="button" class="w-full flex items-center justify-between px-4 py-3 rounded-lg {{ request()->routeIs('pos.*') ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white' : 'text-gray-700 hover:bg-gray-100' }} transition duration-150">
            <div class="flex items-center space-x-3">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="font-medium">Point de vente</span>
            </div>
            <svg :class="{'rotate-180': open}" class="h-4 w-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div x-show="open" x-transition class="ml-4 space-y-1 border-l-2 border-emerald-200 pl-4">
            <a href="{{ route('pos.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('pos.index') ? 'text-emerald-700 bg-emerald-50' : 'text-gray-600 hover:text-emerald-700 hover:bg-emerald-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                <span>Caisse</span>
            </a>
            <a href="{{ route('pos.sales.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('pos.sales.*') ? 'text-emerald-700 bg-emerald-50' : 'text-gray-600 hover:text-emerald-700 hover:bg-emerald-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Historique & paiements</span>
            </a>
        </div>
    </div>

    <div x-data="{ open: {{ request()->is('crm/*') ? 'true' : 'false' }} }" class="space-y-1">
        <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition duration-150">
            <div class="flex items-center space-x-3">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="font-medium">CRM</span>
            </div>
            <svg :class="{'rotate-180': open}" class="h-4 w-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div x-show="open" x-transition class="ml-4 space-y-1 border-l-2 border-gray-200 pl-4">
            <a href="{{ route('clients.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('clients.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span>Clients</span>
            </a>
            <a href="{{ route('suppliers.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('suppliers.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span>Fournisseurs</span>
            </a>
        </div>
    </div>

    <a href="{{ route('products.index') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->routeIs('products.*') ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }} transition duration-150">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
        </svg>
        <span class="font-medium">Gestion produits</span>
    </a>

    <a href="{{ route('stock.index') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->routeIs('stock.*') ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }} transition duration-150">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
        </svg>
        <span class="font-medium">Gestion stock</span>
    </a>

    <div x-data="{ open: {{ request()->is('sales/*') ? 'true' : 'false' }} }" class="space-y-1">
        <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition duration-150">
            <div class="flex items-center space-x-3">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="font-medium">Gestion ventes</span>
            </div>
            <svg :class="{'rotate-180': open}" class="h-4 w-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div x-show="open" x-transition class="ml-4 space-y-1 border-l-2 border-gray-200 pl-4">
            <a href="{{ route('quotes.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('quotes.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Devis</span>
            </a>
            <a href="{{ route('purchase-orders.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('purchase-orders.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>BC</span>
            </a>
            <a href="{{ route('invoices.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('invoices.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Factures</span>
            </a>
            <a href="{{ route('credit-notes.index') }}" class="flex items-center space-x-2 px-3 py-2 rounded text-sm {{ request()->routeIs('credit-notes.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50' }} transition duration-150">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                </svg>
                <span>Avoirs</span>
            </a>
        </div>
    </div>

    <div class="pt-2 mt-2 border-t border-gray-200">
        <p class="px-4 pb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Intégrations</p>
        <a href="{{ route('integrations.shopify.edit') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->routeIs('integrations.shopify.*') ? 'bg-[#96BF48]/15 text-[#5a7a2e] ring-1 ring-[#96BF48]/40' : 'text-gray-700 hover:bg-gray-100' }} transition duration-150">
            <svg class="h-5 w-5 shrink-0 text-[#96BF48]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
            </svg>
            <span class="font-medium">Shopify Integration</span>
        </a>
    </div>
    </nav>

    <div class="flex-shrink-0 p-4 border-t border-gray-200 bg-white">
    <div class="flex items-center space-x-3 mb-3">
        <div class="h-10 w-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-full flex items-center justify-center">
            <span class="text-white font-semibold text-sm">{{ substr(Auth::user()->name, 0, 2) }}</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ Auth::user()->name }}</p>
            <p class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</p>
        </div>
    </div>
    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="w-full flex items-center justify-center space-x-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition duration-150">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            <span>Déconnexion</span>
        </button>
    </form>
</div>
</div>
