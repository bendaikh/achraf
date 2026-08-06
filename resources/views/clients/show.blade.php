@extends('layouts.with-sidebar')

@section('title', 'Détails du client')

@section('sidebar_page_title', 'Client')

@section('main')
@php
    $k = 'text-xs font-medium uppercase tracking-wide text-gray-500';
    $v = 'mt-1 text-sm font-medium text-gray-900';
    $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
@endphp
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen bg-gray-50/80"
      x-data="{ tab: '{{ request('tab', 'infos') }}' }">
    <div class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto">
        <div class="mb-6">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('clients.index') }}" class="hover:text-[#c9920f]">Clients</a>
                <span>/</span>
                <span class="text-gray-900">{{ $client->name }}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="h-12 w-12 rounded-xl bg-[#fdb819]/15 text-[#c9920f] flex items-center justify-center font-bold text-lg shrink-0">
                        {{ strtoupper(mb_substr($client->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $client->name }}</h1>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $client->statusBadgeClass() }}">
                                {{ $client->statusLabel() }}
                            </span>
                            @if($client->is_vip)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">VIP</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $client->code ?? '—' }}
                            · {{ $client->client_type === 'particulier' ? 'Particulier' : 'Entreprise' }}
                            · Créé le {{ $client->created_at?->format('d/m/Y') }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($client->phone || $client->whatsapp)
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', $client->whatsapp ?: $client->phone) }}"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 px-3 py-2 border border-gray-200 bg-white rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                            WhatsApp
                        </a>
                    @endif
                    @if($client->email)
                        <a href="mailto:{{ $client->email }}"
                           class="inline-flex items-center gap-2 px-3 py-2 border border-gray-200 bg-white rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                            Email
                        </a>
                    @endif
                    <a href="{{ route('clients.edit', $client) }}"
                       class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] text-sm font-semibold">
                        Modifier
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- KPI strip --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Factures</p>
                <p class="mt-1 text-xl font-bold text-gray-900">{{ $stats['invoices_count'] }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $fmt($stats['invoices_total']) }} MAD</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Devis</p>
                <p class="mt-1 text-xl font-bold text-gray-900">{{ $stats['quotes_count'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Ventes POS</p>
                <p class="mt-1 text-xl font-bold text-gray-900">{{ $stats['pos_count'] }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $fmt($stats['pos_total']) }} MAD</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Points / Remise</p>
                <p class="mt-1 text-xl font-bold text-gray-900">{{ $client->loyalty_points ?? 0 }} pts</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $client->discount_percent !== null ? $client->discount_percent.' %' : 'Aucune remise' }}</p>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <nav class="flex overflow-x-auto border-b border-gray-200 px-2" aria-label="Fiche client">
                @foreach([
                    'infos' => 'Informations',
                    'historique' => 'Historique',
                    'fidelisation' => 'Fidélisation',
                    'documents' => 'Documents'.($stats['documents_count'] ? ' ('.$stats['documents_count'].')' : ''),
                ] as $key => $label)
                    <button type="button" @click="tab = '{{ $key }}'"
                            class="relative px-4 py-3 text-sm font-medium whitespace-nowrap transition"
                            :class="tab === '{{ $key }}' ? 'text-[#0a5d8a]' : 'text-gray-500 hover:text-gray-900'">
                        {{ $label }}
                        <span x-show="tab === '{{ $key }}'" class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-[#fdb819]"></span>
                    </button>
                @endforeach
            </nav>

            {{-- INFOS --}}
            <div x-show="tab === 'infos'" x-cloak class="p-5 sm:p-6 space-y-8">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Coordonnées</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div><p class="{{ $k }}">Téléphone</p><p class="{{ $v }}">{{ $client->phone ?? '—' }}</p></div>
                        <div><p class="{{ $k }}">WhatsApp</p><p class="{{ $v }}">{{ $client->whatsapp ?? '—' }}</p></div>
                        <div><p class="{{ $k }}">Email</p><p class="{{ $v }}">{{ $client->email ?? '—' }}</p></div>
                    </div>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Adresse</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div class="sm:col-span-2 lg:col-span-3"><p class="{{ $k }}">Adresse</p><p class="{{ $v }}">{{ $client->address ?? '—' }}</p></div>
                        <div><p class="{{ $k }}">Ville</p><p class="{{ $v }}">{{ $client->ville ?? $client->city ?? '—' }}</p></div>
                        <div><p class="{{ $k }}">Région</p><p class="{{ $v }}">{{ $client->region ?? '—' }}</p></div>
                        <div><p class="{{ $k }}">Code postal</p><p class="{{ $v }}">{{ $client->postal_code ?? '—' }}</p></div>
                        <div><p class="{{ $k }}">Pays</p><p class="{{ $v }}">{{ $client->country ?? '—' }}</p></div>
                    </div>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Identité</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        @if($client->client_type === 'particulier')
                            <div><p class="{{ $k }}">Prénom / Nom</p><p class="{{ $v }}">{{ trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: $client->name }}</p></div>
                            <div><p class="{{ $k }}">Date de naissance</p><p class="{{ $v }}">{{ $client->date_of_birth?->format('d/m/Y') ?? '—' }}</p></div>
                            <div><p class="{{ $k }}">CIN</p><p class="{{ $v }}">{{ $client->cin ?? '—' }}</p></div>
                            <div><p class="{{ $k }}">Ville de délivrance</p><p class="{{ $v }}">{{ $client->cin_issue_city ?? '—' }}</p></div>
                        @else
                            <div><p class="{{ $k }}">Raison sociale</p><p class="{{ $v }}">{{ $client->name }}</p></div>
                            <div><p class="{{ $k }}">RC</p><p class="{{ $v }}">{{ $client->rc ?? '—' }}</p></div>
                            <div><p class="{{ $k }}">ICE</p><p class="{{ $v }}">{{ $client->ice ?? '—' }}</p></div>
                            <div><p class="{{ $k }}">IF</p><p class="{{ $v }}">{{ $client->fiscal_identifier ?? '—' }}</p></div>
                        @endif
                    </div>
                </div>
                @if($client->notes)
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 mb-2">Notes internes</h2>
                        <p class="text-sm text-gray-700 whitespace-pre-line bg-gray-50 rounded-lg p-4 border border-gray-100">{{ $client->notes }}</p>
                    </div>
                @endif
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Préférences</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div><p class="{{ $k }}">Paiement</p><p class="{{ $v }}">{{ \App\Models\Client::PAYMENT_METHODS[$client->preferred_payment_method] ?? '—' }}</p></div>
                        <div><p class="{{ $k }}">Livraison</p><p class="{{ $v }}">{{ \App\Models\Client::DELIVERY_MODES[$client->preferred_delivery_mode] ?? '—' }}</p></div>
                        <div><p class="{{ $k }}">Devise</p><p class="{{ $v }}">{{ $client->currency ?? 'MAD' }}</p></div>
                        <div><p class="{{ $k }}">Fréquence</p><p class="{{ $v }}">{{ \App\Models\Client::PURCHASE_FREQUENCIES[$client->purchase_frequency] ?? '—' }}</p></div>
                        <div><p class="{{ $k }}">Plafond</p><p class="{{ $v }}">{{ $client->order_ceiling !== null ? $fmt($client->order_ceiling).' '.($client->currency ?? 'MAD') : '—' }}</p></div>
                    </div>
                </div>
            </div>

            {{-- HISTORIQUE --}}
            <div x-show="tab === 'historique'" x-cloak class="p-5 sm:p-6 space-y-8">
                @php
                    $historySections = [
                        [
                            'title' => 'Factures',
                            'items' => $client->invoices,
                            'empty' => 'Aucune facture',
                            'cols' => function ($row) use ($fmt) {
                                return [
                                    'ref' => $row->invoice_number,
                                    'date' => $row->invoice_date?->format('d/m/Y'),
                                    'total' => $fmt($row->total).' '.($row->currency ?? 'MAD'),
                                    'url' => route('invoices.show', $row),
                                ];
                            },
                        ],
                        [
                            'title' => 'Devis',
                            'items' => $client->quotes,
                            'empty' => 'Aucun devis',
                            'cols' => function ($row) use ($fmt) {
                                return [
                                    'ref' => $row->quote_number,
                                    'date' => $row->quote_date?->format('d/m/Y'),
                                    'total' => $fmt($row->total).' '.($row->currency ?? 'MAD'),
                                    'url' => route('quotes.show', $row),
                                ];
                            },
                        ],
                        [
                            'title' => 'Bons de commande',
                            'items' => $client->purchaseOrders,
                            'empty' => 'Aucun bon de commande',
                            'cols' => function ($row) use ($fmt) {
                                return [
                                    'ref' => $row->reference,
                                    'date' => $row->order_date?->format('d/m/Y') ?? $row->created_at?->format('d/m/Y'),
                                    'total' => $fmt($row->total).' '.($row->currency ?? 'MAD'),
                                    'url' => route('purchase-orders.show', $row),
                                ];
                            },
                        ],
                        [
                            'title' => 'Avoirs',
                            'items' => $client->creditNotes,
                            'empty' => 'Aucun avoir',
                            'cols' => function ($row) use ($fmt) {
                                return [
                                    'ref' => $row->credit_note_number,
                                    'date' => $row->credit_note_date?->format('d/m/Y') ?? $row->created_at?->format('d/m/Y'),
                                    'total' => $fmt($row->total).' '.($row->currency ?? 'MAD'),
                                    'url' => route('credit-notes.show', $row),
                                ];
                            },
                        ],
                        [
                            'title' => 'Ventes POS',
                            'items' => $client->posSales,
                            'empty' => 'Aucune vente POS',
                            'cols' => function ($row) use ($fmt) {
                                return [
                                    'ref' => $row->ticket_number,
                                    'date' => $row->sold_at?->format('d/m/Y H:i') ?? $row->created_at?->format('d/m/Y'),
                                    'total' => $fmt($row->total).' '.($row->currency ?? 'MAD'),
                                    'url' => route('pos.sales.show', $row),
                                ];
                            },
                        ],
                    ];
                @endphp

                @foreach($historySections as $section)
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ $section['title'] }}</h2>
                        @if($section['items']->isEmpty())
                            <p class="text-sm text-gray-500 py-3">{{ $section['empty'] }}</p>
                        @else
                            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-100 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2.5 text-left font-medium text-gray-500">Référence</th>
                                            <th class="px-4 py-2.5 text-left font-medium text-gray-500">Date</th>
                                            <th class="px-4 py-2.5 text-right font-medium text-gray-500">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($section['items'] as $row)
                                            @php $c = ($section['cols'])($row); @endphp
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2.5">
                                                    <a href="{{ $c['url'] }}" class="text-[#0a5d8a] hover:underline font-medium">{{ $c['ref'] ?? '—' }}</a>
                                                </td>
                                                <td class="px-4 py-2.5 text-gray-600">{{ $c['date'] ?? '—' }}</td>
                                                <td class="px-4 py-2.5 text-right font-medium text-gray-900">{{ $c['total'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- FIDELISATION --}}
            <div x-show="tab === 'fidelisation'" x-cloak class="p-5 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div><p class="{{ $k }}">Catégorie</p><p class="{{ $v }}">{{ \App\Models\Client::CATEGORIES[$client->category] ?? '—' }}</p></div>
                    <div><p class="{{ $k }}">Source d'acquisition</p><p class="{{ $v }}">{{ \App\Models\Client::ACQUISITION_SOURCES[$client->acquisition_source] ?? '—' }}</p></div>
                    <div><p class="{{ $k }}">Client VIP</p><p class="{{ $v }}">{{ $client->is_vip ? 'Oui' : 'Non' }}</p></div>
                    <div><p class="{{ $k }}">Remise permanente</p><p class="{{ $v }}">{{ $client->discount_percent !== null ? $client->discount_percent.' %' : '—' }}</p></div>
                    <div><p class="{{ $k }}">Points de fidélité</p><p class="{{ $v }}">{{ $client->loyalty_points ?? 0 }}</p></div>
                </div>
            </div>

            {{-- DOCUMENTS --}}
            <div x-show="tab === 'documents'" x-cloak class="p-5 sm:p-6">
                @if($client->documents->isEmpty())
                    <p class="text-sm text-gray-500">Aucun document joint. <a href="{{ route('clients.edit', $client) }}" class="text-[#0a5d8a] hover:underline">Ajouter depuis la modification</a>.</p>
                @else
                    <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                        @foreach($client->documents as $document)
                            <li class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50">
                                <a href="{{ $document->url() }}" target="_blank" class="text-sm text-blue-600 hover:underline truncate">{{ $document->original_name }}</a>
                                <span class="text-xs text-gray-400 shrink-0">{{ $document->created_at?->format('d/m/Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</main>
@endsection
