@props([
    'idPrefix' => '',
    'supplier' => null,
    'supplierCode' => null,
    'users' => [],
    'compact' => false,
    'formAction' => null,
    'formMethod' => 'POST',
    'submitLabel' => 'Enregistrer',
    'cancelUrl' => null,
])

@php
    $val = function (string $field, $default = null) use ($supplier) {
        return old($field, $supplier?->{$field} ?? $default);
    };

    $input = 'w-full px-3 py-2.5 border border-gray-200 rounded-lg bg-white text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition';
    $label = 'block text-sm font-medium text-gray-700 mb-1.5';
    $hint = 'mt-1 text-xs text-gray-400';

    $sections = [
        ['id' => 'generales', 'n' => 1, 'title' => 'Informations générales', 'desc' => 'Identité et coordonnées', 'badge' => 'bg-blue-500', 'ring' => 'border-l-blue-500'],
        ['id' => 'juridiques', 'n' => 2, 'title' => 'Informations juridiques', 'desc' => 'RC, ICE, IF, TP', 'badge' => 'bg-emerald-500', 'ring' => 'border-l-emerald-500'],
        ['id' => 'contact', 'n' => 3, 'title' => 'Contact principal', 'desc' => 'Personne à contacter', 'badge' => 'bg-violet-500', 'ring' => 'border-l-violet-500'],
        ['id' => 'bancaires', 'n' => 4, 'title' => 'Coordonnées bancaires', 'desc' => 'Paiements et virements', 'badge' => 'bg-amber-500', 'ring' => 'border-l-amber-500'],
        ['id' => 'commerciales', 'n' => 5, 'title' => 'Conditions commerciales', 'desc' => 'Paiement et délais', 'badge' => 'bg-cyan-500', 'ring' => 'border-l-cyan-500'],
        ['id' => 'interne', 'n' => 6, 'title' => 'Gestion interne', 'desc' => 'Statut et classification', 'badge' => 'bg-rose-500', 'ring' => 'border-l-rose-500'],
        ['id' => 'documents', 'n' => 7, 'title' => 'Documents joints', 'desc' => 'Fichiers PDF / images', 'badge' => 'bg-slate-600', 'ring' => 'border-l-slate-500'],
    ];

    $codeDisplay = $supplier?->code ?? $supplierCode ?? 'FRN…';
    $cancelUrl = $cancelUrl ?? route('suppliers.index');
@endphp

@if($compact)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label for="{{ $idPrefix }}name" class="{{ $label }}">Nom du fournisseur <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="{{ $idPrefix }}name" value="{{ $val('name') }}" required class="{{ $input }}" placeholder="Ex: Société Atlas Distribution">
        </div>
        <div>
            <label for="{{ $idPrefix }}phone" class="{{ $label }}">Téléphone</label>
            <input type="text" name="phone" id="{{ $idPrefix }}phone" value="{{ $val('phone') }}" class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $idPrefix }}email" class="{{ $label }}">Email</label>
            <input type="email" name="email" id="{{ $idPrefix }}email" value="{{ $val('email') }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-2">
            <label for="{{ $idPrefix }}address" class="{{ $label }}">Adresse</label>
            <input type="text" name="address" id="{{ $idPrefix }}address" value="{{ $val('address') }}" class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $idPrefix }}ville" class="{{ $label }}">Ville</label>
            <input type="text" name="ville" id="{{ $idPrefix }}ville" value="{{ $val('ville') }}" class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $idPrefix }}ice" class="{{ $label }}">ICE</label>
            <input type="text" name="ice" id="{{ $idPrefix }}ice" value="{{ $val('ice') }}" class="{{ $input }}">
        </div>
        <input type="hidden" name="country" value="Maroc">
        <input type="hidden" name="status" value="actif">
    </div>
@else
<form
    action="{{ $formAction }}"
    method="POST"
    enctype="multipart/form-data"
    x-data="{
        active: 'generales',
        scrollTo(id) {
            this.active = id;
            const el = document.getElementById('section-' + id);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }"
>
    @csrf
    @if(strtoupper($formMethod) !== 'POST')
        @method($formMethod)
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-[210px_minmax(0,1fr)_250px] gap-5 items-start">
        {{-- Left nav --}}
        <nav class="hidden xl:block sticky top-4 space-y-1 self-start">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 px-2 mb-3">Sections</p>
            @foreach($sections as $s)
                <button
                    type="button"
                    @click="scrollTo('{{ $s['id'] }}')"
                    :class="active === '{{ $s['id'] }}' ? 'bg-white shadow-sm border-gray-200' : 'border-transparent hover:bg-white/80'"
                    class="w-full text-left flex gap-3 p-2.5 rounded-xl border transition"
                >
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white {{ $s['badge'] }}">{{ $s['n'] }}</span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-900 leading-tight">{{ $s['title'] }}</span>
                        <span class="block text-xs text-gray-500 mt-0.5 truncate">{{ $s['desc'] }}</span>
                    </span>
                </button>
            @endforeach
        </nav>

        {{-- Main form sections --}}
        <div class="space-y-5 min-w-0">
            {{-- Mobile chips --}}
            <div class="xl:hidden -mx-1 overflow-x-auto pb-1">
                <div class="flex gap-2 px-1 min-w-max">
                    @foreach($sections as $s)
                        <button type="button" @click="scrollTo('{{ $s['id'] }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-white border border-gray-200 text-gray-700 shadow-sm">
                            <span class="h-5 w-5 rounded-full text-[10px] font-bold text-white flex items-center justify-center {{ $s['badge'] }}">{{ $s['n'] }}</span>
                            {{ \Illuminate\Support\Str::limit($s['title'], 18) }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- 1 Générales --}}
            <section id="section-generales" class="bg-white rounded-xl border border-gray-200 border-l-4 {{ $sections[0]['ring'] }} shadow-sm scroll-mt-4">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white {{ $sections[0]['badge'] }}">1</span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Informations générales</h2>
                        <p class="text-xs text-gray-500">Identité et coordonnées du fournisseur</p>
                    </div>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="{{ $idPrefix }}name" class="{{ $label }}">Nom du fournisseur <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="{{ $idPrefix }}name" value="{{ $val('name') }}" required class="{{ $input }} @error('name') border-red-400 @enderror" placeholder="Ex: Société Atlas Distribution">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Code fournisseur</label>
                        <input type="text" value="{{ $codeDisplay }}" readonly class="{{ $input }} bg-gray-50 text-gray-600 cursor-not-allowed">
                        <p class="{{ $hint }}">Généré automatiquement</p>
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}legal_name" class="{{ $label }}">Raison sociale</label>
                        <input type="text" name="legal_name" id="{{ $idPrefix }}legal_name" value="{{ $val('legal_name') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}trade_name" class="{{ $label }}">Nom commercial</label>
                        <input type="text" name="trade_name" id="{{ $idPrefix }}trade_name" value="{{ $val('trade_name') }}" class="{{ $input }}">
                    </div>
                    <div class="md:col-span-2">
                        <label for="{{ $idPrefix }}address" class="{{ $label }}">Adresse</label>
                        <input type="text" name="address" id="{{ $idPrefix }}address" value="{{ $val('address') }}" class="{{ $input }}" placeholder="Adresse complète">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}ville" class="{{ $label }}">Ville</label>
                        <input type="text" name="ville" id="{{ $idPrefix }}ville" value="{{ $val('ville') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}region" class="{{ $label }}">Région</label>
                        <input type="text" name="region" id="{{ $idPrefix }}region" value="{{ $val('region') }}" class="{{ $input }}" list="{{ $idPrefix }}regions-list">
                        <datalist id="{{ $idPrefix }}regions-list">
                            <option value="Casablanca-Settat">
                            <option value="Rabat-Salé-Kénitra">
                            <option value="Marrakech-Safi">
                            <option value="Fès-Meknès">
                            <option value="Tanger-Tétouan-Al Hoceïma">
                            <option value="Souss-Massa">
                            <option value="Oriental">
                            <option value="Béni Mellal-Khénifra">
                            <option value="Drâa-Tafilalet">
                            <option value="Guelmim-Oued Noun">
                            <option value="Laâyoune-Sakia El Hamra">
                            <option value="Dakhla-Oued Ed-Dahab">
                        </datalist>
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}postal_code" class="{{ $label }}">Code postal</label>
                        <input type="text" name="postal_code" id="{{ $idPrefix }}postal_code" value="{{ $val('postal_code') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}country" class="{{ $label }}">Pays</label>
                        <select name="country" id="{{ $idPrefix }}country" class="{{ $input }}">
                            <option value="Maroc" {{ $val('country', 'Maroc') == 'Maroc' ? 'selected' : '' }}>Maroc</option>
                        </select>
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}phone" class="{{ $label }}">Téléphone</label>
                        <input type="text" name="phone" id="{{ $idPrefix }}phone" value="{{ $val('phone') }}" class="{{ $input }}" placeholder="+212 5XX-XXXXXX">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}whatsapp" class="{{ $label }}">WhatsApp</label>
                        <input type="text" name="whatsapp" id="{{ $idPrefix }}whatsapp" value="{{ $val('whatsapp') }}" class="{{ $input }}" placeholder="+212 6XX-XXXXXX">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}email" class="{{ $label }}">Email</label>
                        <input type="email" name="email" id="{{ $idPrefix }}email" value="{{ $val('email') }}" class="{{ $input }} @error('email') border-red-400 @enderror" placeholder="contact@exemple.ma">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}website" class="{{ $label }}">Site web</label>
                        <input type="text" name="website" id="{{ $idPrefix }}website" value="{{ $val('website') }}" class="{{ $input }}" placeholder="https://">
                    </div>
                </div>
            </section>

            {{-- 2 Juridiques --}}
            <section id="section-juridiques" class="bg-white rounded-xl border border-gray-200 border-l-4 {{ $sections[1]['ring'] }} shadow-sm scroll-mt-4">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white {{ $sections[1]['badge'] }}">2</span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Informations juridiques</h2>
                        <p class="text-xs text-gray-500">Identifiants légaux et fiscaux</p>
                    </div>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="{{ $idPrefix }}rc" class="{{ $label }}">Registre de Commerce (RC)</label>
                        <input type="text" name="rc" id="{{ $idPrefix }}rc" value="{{ $val('rc') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}rc_city" class="{{ $label }}">Ville du RC</label>
                        <input type="text" name="rc_city" id="{{ $idPrefix }}rc_city" value="{{ $val('rc_city') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}ice" class="{{ $label }}">ICE</label>
                        <input type="text" name="ice" id="{{ $idPrefix }}ice" value="{{ $val('ice') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}fiscal_identifier" class="{{ $label }}">Identifiant Fiscal (IF)</label>
                        <input type="text" name="fiscal_identifier" id="{{ $idPrefix }}fiscal_identifier" value="{{ $val('fiscal_identifier') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}tp" class="{{ $label }}">Taxe Professionnelle (TP)</label>
                        <input type="text" name="tp" id="{{ $idPrefix }}tp" value="{{ $val('tp') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}legal_form" class="{{ $label }}">Forme juridique</label>
                        <select name="legal_form" id="{{ $idPrefix }}legal_form" class="{{ $input }}">
                            <option value="">Sélectionner</option>
                            @foreach(\App\Models\Supplier::LEGAL_FORMS as $key => $lab)
                                <option value="{{ $key }}" {{ $val('legal_form') == $key ? 'selected' : '' }}>{{ $lab }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}company_created_at" class="{{ $label }}">Date de création</label>
                        <input type="date" name="company_created_at" id="{{ $idPrefix }}company_created_at"
                            value="{{ $val('company_created_at') ? \Illuminate\Support\Carbon::parse($val('company_created_at'))->format('Y-m-d') : '' }}"
                            class="{{ $input }}">
                    </div>
                </div>
            </section>

            {{-- 3 Contact --}}
            <section id="section-contact" class="bg-white rounded-xl border border-gray-200 border-l-4 {{ $sections[2]['ring'] }} shadow-sm scroll-mt-4">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white {{ $sections[2]['badge'] }}">3</span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Contact principal</h2>
                        <p class="text-xs text-gray-500">Interlocuteur chez le fournisseur</p>
                    </div>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="{{ $idPrefix }}contact_name" class="{{ $label }}">Nom du contact</label>
                        <input type="text" name="contact_name" id="{{ $idPrefix }}contact_name" value="{{ $val('contact_name') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}contact_role" class="{{ $label }}">Fonction</label>
                        <input type="text" name="contact_role" id="{{ $idPrefix }}contact_role" value="{{ $val('contact_role') }}" class="{{ $input }}" placeholder="Ex: Responsable commercial">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}contact_phone" class="{{ $label }}">Téléphone</label>
                        <input type="text" name="contact_phone" id="{{ $idPrefix }}contact_phone" value="{{ $val('contact_phone') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}contact_mobile" class="{{ $label }}">Mobile</label>
                        <input type="text" name="contact_mobile" id="{{ $idPrefix }}contact_mobile" value="{{ $val('contact_mobile') }}" class="{{ $input }}">
                    </div>
                    <div class="md:col-span-2">
                        <label for="{{ $idPrefix }}contact_email" class="{{ $label }}">Email</label>
                        <input type="email" name="contact_email" id="{{ $idPrefix }}contact_email" value="{{ $val('contact_email') }}" class="{{ $input }}">
                    </div>
                </div>
            </section>

            {{-- 4 Bancaires --}}
            <section id="section-bancaires" class="bg-white rounded-xl border border-gray-200 border-l-4 {{ $sections[3]['ring'] }} shadow-sm scroll-mt-4">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white {{ $sections[3]['badge'] }}">4</span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Coordonnées bancaires</h2>
                        <p class="text-xs text-gray-500">Pour les virements et règlements</p>
                    </div>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="{{ $idPrefix }}bank_name" class="{{ $label }}">Banque</label>
                        <input type="text" name="bank_name" id="{{ $idPrefix }}bank_name" value="{{ $val('bank_name') }}" class="{{ $input }}" list="{{ $idPrefix }}banks-list" placeholder="Ex: Attijariwafa Bank">
                        <datalist id="{{ $idPrefix }}banks-list">
                            <option value="Attijariwafa Bank">
                            <option value="Banque Populaire">
                            <option value="BMCE Bank Of Africa">
                            <option value="CIH Bank">
                            <option value="Crédit Agricole du Maroc">
                            <option value="Société Générale Maroc">
                            <option value="CFG Bank">
                        </datalist>
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}bank_account_holder" class="{{ $label }}">Titulaire du compte</label>
                        <input type="text" name="bank_account_holder" id="{{ $idPrefix }}bank_account_holder" value="{{ $val('bank_account_holder') }}" class="{{ $input }}">
                    </div>
                    <div class="md:col-span-2">
                        <label for="{{ $idPrefix }}rib" class="{{ $label }}">RIB</label>
                        <input type="text" name="rib" id="{{ $idPrefix }}rib" value="{{ $val('rib') }}" class="{{ $input }} font-mono" placeholder="XXX XXX XXXXXXXXXXXXXXXX XX">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}iban" class="{{ $label }}">IBAN</label>
                        <input type="text" name="iban" id="{{ $idPrefix }}iban" value="{{ $val('iban') }}" class="{{ $input }} font-mono">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}swift_bic" class="{{ $label }}">SWIFT / BIC</label>
                        <input type="text" name="swift_bic" id="{{ $idPrefix }}swift_bic" value="{{ $val('swift_bic') }}" class="{{ $input }} font-mono">
                    </div>
                </div>
            </section>

            {{-- 5 Commerciales --}}
            <section id="section-commerciales" class="bg-white rounded-xl border border-gray-200 border-l-4 {{ $sections[4]['ring'] }} shadow-sm scroll-mt-4">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white {{ $sections[4]['badge'] }}">5</span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Conditions commerciales</h2>
                        <p class="text-xs text-gray-500">Paiement, devises et délais</p>
                    </div>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="{{ $idPrefix }}payment_method" class="{{ $label }}">Mode de paiement</label>
                        <select name="payment_method" id="{{ $idPrefix }}payment_method" class="{{ $input }}">
                            <option value="">Sélectionner</option>
                            @foreach(\App\Models\Supplier::PAYMENT_METHODS as $key => $lab)
                                <option value="{{ $key }}" {{ $val('payment_method') == $key ? 'selected' : '' }}>{{ $lab }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}payment_terms" class="{{ $label }}">Délai de paiement</label>
                        <select name="payment_terms" id="{{ $idPrefix }}payment_terms" class="{{ $input }}">
                            <option value="">Sélectionner</option>
                            @foreach(\App\Models\Supplier::PAYMENT_TERMS as $key => $lab)
                                <option value="{{ $key }}" {{ $val('payment_terms') == $key ? 'selected' : '' }}>{{ $lab }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}currency" class="{{ $label }}">Devise</label>
                        <select name="currency" id="{{ $idPrefix }}currency" class="{{ $input }}">
                            @php $currency = $val('currency', 'MAD'); @endphp
                            <option value="MAD" {{ $currency == 'MAD' ? 'selected' : '' }}>MAD — Dirham Marocain</option>
                            <option value="EUR" {{ $currency == 'EUR' ? 'selected' : '' }}>EUR — Euro</option>
                            <option value="USD" {{ $currency == 'USD' ? 'selected' : '' }}>USD — Dollar US</option>
                        </select>
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}discount_percent" class="{{ $label }}">Remise fournisseur (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount_percent" id="{{ $idPrefix }}discount_percent" value="{{ $val('discount_percent') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}min_order_amount" class="{{ $label }}">Montant minimum de commande</label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" name="min_order_amount" id="{{ $idPrefix }}min_order_amount" value="{{ $val('min_order_amount') }}" class="{{ $input }} pr-14">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-gray-400">MAD</span>
                        </div>
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}delivery_lead_days" class="{{ $label }}">Délai de livraison</label>
                        <div class="relative">
                            <input type="number" min="0" name="delivery_lead_days" id="{{ $idPrefix }}delivery_lead_days" value="{{ $val('delivery_lead_days') }}" class="{{ $input }} pr-14">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-gray-400">jours</span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 6 Interne (in main flow so fields submit once; sticky card style on xl via right tip only) --}}
            <section id="section-interne" class="bg-white rounded-xl border border-gray-200 border-l-4 {{ $sections[5]['ring'] }} shadow-sm scroll-mt-4">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white {{ $sections[5]['badge'] }}">6</span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Gestion interne</h2>
                        <p class="text-xs text-gray-500">Statut et classification</p>
                    </div>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    @include('components.partials.supplier-internal-fields', [
                        'idPrefix' => $idPrefix,
                        'val' => $val,
                        'input' => $input,
                        'label' => $label,
                        'users' => $users,
                    ])
                </div>
            </section>

            {{-- 7 Documents --}}
            <section id="section-documents" class="bg-white rounded-xl border border-gray-200 border-l-4 {{ $sections[6]['ring'] }} shadow-sm scroll-mt-4">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white {{ $sections[6]['badge'] }}">7</span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Documents joints</h2>
                        <p class="text-xs text-gray-500">PDF, JPG, PNG — max. 5 Mo par fichier</p>
                    </div>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach(\App\Models\Supplier::DOCUMENT_FIELDS as $field => $docLabel)
                        <label for="{{ $idPrefix }}{{ $field }}" class="group relative flex flex-col gap-2 rounded-xl border border-dashed border-gray-300 bg-gray-50/60 p-4 cursor-pointer hover:border-blue-400 hover:bg-blue-50/40 transition">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-medium text-gray-800 leading-snug">{{ $docLabel }}</span>
                                <svg class="h-5 w-5 text-gray-400 group-hover:text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            @if($supplier && $supplier->{$field})
                                <a href="{{ $supplier->documentUrl($field) }}" target="_blank" class="text-xs text-blue-600 hover:underline" onclick="event.stopPropagation()">Fichier actuel</a>
                            @else
                                <span class="text-xs text-gray-400">PDF, JPG, PNG</span>
                            @endif
                            <span class="text-xs font-medium text-blue-600">Parcourir…</span>
                            <input type="file" name="{{ $field }}" id="{{ $idPrefix }}{{ $field }}" accept=".pdf,.jpg,.jpeg,.png" class="sr-only"
                                onchange="const n=this.closest('label').querySelector('[data-file-name]'); if(n) n.textContent=this.files[0]?this.files[0].name:'';">
                            <span data-file-name class="text-xs text-gray-500 truncate"></span>
                            @error($field)<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Right: aide + parcours --}}
        <aside class="hidden xl:block space-y-4 sticky top-4 self-start">
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-800 mb-1">Aide</p>
                <p class="text-sm text-amber-900/80 leading-relaxed">
                    Les champs marqués d’un <span class="text-red-500 font-semibold">*</span> sont obligatoires. Utilisez le menu de gauche pour naviguer rapidement entre les sections.
                </p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">Parcours</p>
                <ul class="space-y-2">
                    @foreach($sections as $s)
                        <li>
                            <button type="button" @click="scrollTo('{{ $s['id'] }}')" class="w-full flex items-center gap-2 text-left text-sm text-gray-700 hover:text-gray-900">
                                <span class="h-5 w-5 rounded-full text-[10px] font-bold text-white flex items-center justify-center {{ $s['badge'] }}">{{ $s['n'] }}</span>
                                <span class="truncate">{{ $s['title'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </div>

    <div class="mt-6 sticky bottom-0 z-10 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-4 bg-white/95 backdrop-blur border-t border-gray-200 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
        <a href="{{ $cancelUrl }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Annuler
        </a>
        <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium shadow-sm">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
            {{ $submitLabel }}
        </button>
    </div>
</form>
@endif
