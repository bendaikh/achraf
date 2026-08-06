@props([
    'client' => null,
    'clientCode' => '',
    'idPrefix' => '',
    'compact' => false,
])

@php
    $splitName = function (?string $name): array {
        $name = trim((string) $name);
        if ($name === '') {
            return ['', ''];
        }
        $parts = preg_split('/\s+/', $name, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    };

    [$legacyFirst, $legacyLast] = $splitName($client?->name);

    $val = function (string $field, $default = null) use ($client, $legacyFirst, $legacyLast) {
        $fallback = $client?->{$field} ?? $default;
        if ($field === 'first_name' && blank($fallback) && filled($legacyFirst)) {
            $fallback = $legacyFirst;
        }
        if ($field === 'last_name' && blank($fallback) && filled($legacyLast)) {
            $fallback = $legacyLast;
        }
        if ($field === 'date_of_birth' && $fallback instanceof \Carbon\CarbonInterface) {
            $fallback = $fallback->format('Y-m-d');
        }
        return old($field, $fallback);
    };

    $input = 'w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-[#fdb819]/40 focus:border-[#fdb819] transition';
    $label = 'block text-sm font-medium text-gray-700 mb-1.5';
    $required = '<span class="text-red-500">*</span>';
@endphp

{{-- 1. Informations générales --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-[#c9920f]">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </span>
        <div>
            <h2 class="text-base font-semibold text-gray-900">Informations générales</h2>
            <p class="text-xs text-gray-500">Type, statut et identité du client</p>
        </div>
    </div>
    <div class="p-5 space-y-5">
        <div>
            <p class="{{ $label }}">Type de client</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="flex items-center gap-3 p-3.5 border rounded-xl cursor-pointer transition"
                       :class="clientType === 'particulier' ? 'border-[#fdb819] bg-[#fdb819]/10 ring-1 ring-[#fdb819]/30' : 'border-gray-200 hover:border-gray-300'">
                    <input type="radio" name="client_type" value="particulier" x-model="clientType" class="text-[#fdb819] focus:ring-[#fdb819]">
                    <div>
                        <span class="font-medium text-gray-900 text-sm">Particulier</span>
                        <p class="text-xs text-gray-500">Client individuel</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3.5 border rounded-xl cursor-pointer transition"
                       :class="clientType === 'entreprise' ? 'border-[#fdb819] bg-[#fdb819]/10 ring-1 ring-[#fdb819]/30' : 'border-gray-200 hover:border-gray-300'">
                    <input type="radio" name="client_type" value="entreprise" x-model="clientType" class="text-[#fdb819] focus:ring-[#fdb819]">
                    <div>
                        <span class="font-medium text-gray-900 text-sm">Entreprise</span>
                        <p class="text-xs text-gray-500">Société / professionnel</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="{{ $idPrefix }}status" class="{{ $label }}">Statut</label>
                <select name="status" id="{{ $idPrefix }}status" class="{{ $input }} @error('status') border-red-500 @enderror">
                    @foreach(\App\Models\Client::STATUSES as $key => $labelText)
                        <option value="{{ $key }}" {{ $val('status', 'actif') == $key ? 'selected' : '' }}>{{ $labelText }}</option>
                    @endforeach
                </select>
                @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div x-show="clientType === 'particulier'" x-cloak class="md:col-span-2 contents">
                <div x-show="clientType === 'particulier'" x-cloak>
                    <label for="{{ $idPrefix }}first_name" class="{{ $label }}">Prénom {!! $required !!}</label>
                    <input type="text" name="first_name" id="{{ $idPrefix }}first_name" value="{{ $val('first_name') }}"
                           placeholder="Ex : Youssef"
                           x-bind:required="clientType === 'particulier'"
                           class="{{ $input }} @error('first_name') border-red-500 @enderror">
                    @error('first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div x-show="clientType === 'particulier'" x-cloak>
                    <label for="{{ $idPrefix }}last_name" class="{{ $label }}">Nom {!! $required !!}</label>
                    <input type="text" name="last_name" id="{{ $idPrefix }}last_name" value="{{ $val('last_name') }}"
                           placeholder="Ex : Benali"
                           x-bind:required="clientType === 'particulier'"
                           class="{{ $input }} @error('last_name') border-red-500 @enderror">
                    @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div x-show="clientType === 'entreprise'" x-cloak class="md:col-span-2">
                <label for="{{ $idPrefix }}name" class="{{ $label }}">Raison sociale {!! $required !!}</label>
                <input type="text" name="name" id="{{ $idPrefix }}name" value="{{ $val('name') }}"
                       placeholder="Ex : Société Atlas SARL"
                       x-bind:required="clientType === 'entreprise'"
                       class="{{ $input }} @error('name') border-red-500 @enderror">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div x-show="clientType === 'particulier'" x-cloak>
                <label for="{{ $idPrefix }}date_of_birth" class="{{ $label }}">Date de naissance <span class="text-gray-400 font-normal">(optionnel)</span></label>
                <input type="date" name="date_of_birth" id="{{ $idPrefix }}date_of_birth" value="{{ $val('date_of_birth') }}"
                       class="{{ $input }} @error('date_of_birth') border-red-500 @enderror">
                @error('date_of_birth')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>
</section>

{{-- 2. Coordonnées --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </span>
        <div>
            <h2 class="text-base font-semibold text-gray-900">Coordonnées de contact</h2>
            <p class="text-xs text-gray-500">Téléphone, WhatsApp et email</p>
        </div>
    </div>
    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="{{ $idPrefix }}phone" class="{{ $label }}">Téléphone</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </span>
                <input type="text" name="phone" id="{{ $idPrefix }}phone" value="{{ $val('phone') }}"
                       placeholder="06 12 34 56 78"
                       class="{{ $input }} pl-10 @error('phone') border-red-500 @enderror">
            </div>
            @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}whatsapp" class="{{ $label }}">WhatsApp <span class="text-gray-400 font-normal">(optionnel)</span></label>
            <input type="text" name="whatsapp" id="{{ $idPrefix }}whatsapp" value="{{ $val('whatsapp') }}"
                   placeholder="06 12 34 56 78"
                   class="{{ $input }} @error('whatsapp') border-red-500 @enderror">
            @error('whatsapp')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}email" class="{{ $label }}">Email</label>
            <input type="email" name="email" id="{{ $idPrefix }}email" value="{{ $val('email') }}"
                   placeholder="client@email.ma"
                   class="{{ $input }} @error('email') border-red-500 @enderror">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        @unless($compact)
            <div>
                <label for="{{ $idPrefix }}email_confirmation" class="{{ $label }}">Confirmer l'email</label>
                <input type="email" name="email_confirmation" id="{{ $idPrefix }}email_confirmation" value="{{ old('email_confirmation', $val('email')) }}"
                       placeholder="client@email.ma"
                       class="{{ $input }} @error('email_confirmation') border-red-500 @enderror">
                @error('email_confirmation')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endunless
    </div>
</section>

{{-- 3. Adresse --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50 text-orange-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </span>
        <div>
            <h2 class="text-base font-semibold text-gray-900">Adresse</h2>
            <p class="text-xs text-gray-500">Localisation du client</p>
        </div>
    </div>
    <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-3">
            <label for="{{ $idPrefix }}address" class="{{ $label }}">Adresse</label>
            <input type="text" name="address" id="{{ $idPrefix }}address" value="{{ $val('address') }}"
                   placeholder="Ex : 123, Avenue Hassan II"
                   class="{{ $input }} @error('address') border-red-500 @enderror">
            @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}ville" class="{{ $label }}">Ville</label>
            <input type="text" name="ville" id="{{ $idPrefix }}ville" value="{{ $val('ville') }}"
                   placeholder="Ex : Casablanca"
                   class="{{ $input }} @error('ville') border-red-500 @enderror">
            @error('ville')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}region" class="{{ $label }}">Région</label>
            <select name="region" id="{{ $idPrefix }}region" class="{{ $input }} @error('region') border-red-500 @enderror">
                <option value="">Choisir une région</option>
                @foreach(\App\Models\Client::REGIONS as $region)
                    <option value="{{ $region }}" {{ $val('region') == $region ? 'selected' : '' }}>{{ $region }}</option>
                @endforeach
            </select>
            @error('region')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}postal_code" class="{{ $label }}">Code postal</label>
            <input type="text" name="postal_code" id="{{ $idPrefix }}postal_code" value="{{ $val('postal_code') }}"
                   placeholder="Ex : 20000"
                   class="{{ $input }} @error('postal_code') border-red-500 @enderror">
            @error('postal_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}country" class="{{ $label }}">Pays</label>
            <select name="country" id="{{ $idPrefix }}country" class="{{ $input }} @error('country') border-red-500 @enderror">
                <option value="Maroc" {{ $val('country', 'Maroc') == 'Maroc' ? 'selected' : '' }}>Maroc</option>
            </select>
            @error('country')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        @unless($compact)
            <div>
                <label for="{{ $idPrefix }}latitude" class="{{ $label }}">Latitude <span class="text-gray-400 font-normal">(optionnel)</span></label>
                <input type="text" name="latitude" id="{{ $idPrefix }}latitude" value="{{ $val('latitude') }}" placeholder="33.5731"
                       class="{{ $input }} @error('latitude') border-red-500 @enderror">
                @error('latitude')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="{{ $idPrefix }}longitude" class="{{ $label }}">Longitude <span class="text-gray-400 font-normal">(optionnel)</span></label>
                <input type="text" name="longitude" id="{{ $idPrefix }}longitude" value="{{ $val('longitude') }}" placeholder="-7.5898"
                       class="{{ $input }} @error('longitude') border-red-500 @enderror">
                @error('longitude')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endunless
    </div>
</section>

{{-- 4. Identité --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
        </span>
        <div>
            <h2 class="text-base font-semibold text-gray-900">Identité</h2>
            <p class="text-xs text-gray-500" x-text="clientType === 'entreprise' ? 'RC, ICE, IF' : 'CIN et ville de délivrance'"></p>
        </div>
    </div>
    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div x-show="clientType === 'particulier'" x-cloak>
            <label for="{{ $idPrefix }}cin" class="{{ $label }}">CNI / CIN</label>
            <input type="text" name="cin" id="{{ $idPrefix }}cin" value="{{ $val('cin') }}"
                   placeholder="Ex : BE123456"
                   class="{{ $input }} @error('cin') border-red-500 @enderror">
            @error('cin')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div x-show="clientType === 'particulier'" x-cloak>
            <label for="{{ $idPrefix }}cin_issue_city" class="{{ $label }}">Ville de délivrance</label>
            <input type="text" name="cin_issue_city" id="{{ $idPrefix }}cin_issue_city" value="{{ $val('cin_issue_city') }}"
                   placeholder="Ex : Casablanca"
                   class="{{ $input }} @error('cin_issue_city') border-red-500 @enderror">
            @error('cin_issue_city')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div x-show="clientType === 'entreprise'" x-cloak>
            <label for="{{ $idPrefix }}rc" class="{{ $label }}">Registre de Commerce (RC)</label>
            <input type="text" name="rc" id="{{ $idPrefix }}rc" value="{{ $val('rc') }}"
                   class="{{ $input }} @error('rc') border-red-500 @enderror">
            @error('rc')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div x-show="clientType === 'entreprise'" x-cloak>
            <label for="{{ $idPrefix }}ice" class="{{ $label }}">ICE</label>
            <input type="text" name="ice" id="{{ $idPrefix }}ice" value="{{ $val('ice') }}"
                   class="{{ $input }} @error('ice') border-red-500 @enderror">
            @error('ice')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div x-show="clientType === 'entreprise'" x-cloak>
            <label for="{{ $idPrefix }}fiscal_identifier" class="{{ $label }}">Identifiant fiscal (IF)</label>
            <input type="text" name="fiscal_identifier" id="{{ $idPrefix }}fiscal_identifier" value="{{ $val('fiscal_identifier') }}"
                   class="{{ $input }} @error('fiscal_identifier') border-red-500 @enderror">
            @error('fiscal_identifier')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div x-show="clientType === 'entreprise'" x-cloak>
            <label for="{{ $idPrefix }}tax_id" class="{{ $label }}">Tax ID / Patente <span class="text-gray-400 font-normal">(optionnel)</span></label>
            <input type="text" name="tax_id" id="{{ $idPrefix }}tax_id" value="{{ $val('tax_id') }}"
                   class="{{ $input }} @error('tax_id') border-red-500 @enderror">
            @error('tax_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

@unless($compact)
{{-- 5. Informations complémentaires --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </span>
        <div>
            <h2 class="text-base font-semibold text-gray-900">Informations complémentaires</h2>
            <p class="text-xs text-gray-500">Code, date de création et notes</p>
        </div>
    </div>
    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="{{ $idPrefix }}code" class="{{ $label }}">Code client</label>
            <input type="text" id="{{ $idPrefix }}code" value="{{ $client?->code ?? $clientCode }}" readonly
                   class="{{ $input }} bg-gray-50 text-gray-600 cursor-not-allowed">
            <p class="mt-1 text-xs text-gray-500">Généré automatiquement</p>
        </div>
        <div>
            <label class="{{ $label }}">Date de création</label>
            <input type="text" value="{{ $client?->created_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}" readonly
                   class="{{ $input }} bg-gray-50 text-gray-600 cursor-not-allowed">
        </div>
        <div class="md:col-span-2">
            <label for="{{ $idPrefix }}notes" class="{{ $label }}">Notes internes <span class="text-gray-400 font-normal">(optionnel)</span></label>
            <textarea name="notes" id="{{ $idPrefix }}notes" rows="3" placeholder="Ex : Client VIP, préfère les appels"
                      class="{{ $input }} @error('notes') border-red-500 @enderror">{{ $val('notes') }}</textarea>
            @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

{{-- 6. Fidélisation --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            </span>
            <div>
                <h2 class="text-base font-semibold text-gray-900">Fidélisation</h2>
                <p class="text-xs text-gray-500">Catégorie, source et avantages</p>
            </div>
        </div>
        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
            <span class="text-sm text-gray-700">Client VIP</span>
            <input type="hidden" name="is_vip" value="0">
            <input type="checkbox" name="is_vip" value="1" {{ $val('is_vip') ? 'checked' : '' }}
                   class="sr-only peer">
            <span class="relative w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-[#fdb819] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></span>
        </label>
    </div>
    <div class="p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label for="{{ $idPrefix }}category" class="{{ $label }}">Catégorie client</label>
            <select name="category" id="{{ $idPrefix }}category" class="{{ $input }} @error('category') border-red-500 @enderror">
                <option value="">Sélectionner</option>
                @foreach(\App\Models\Client::CATEGORIES as $key => $labelText)
                    <option value="{{ $key }}" {{ $val('category') == $key ? 'selected' : '' }}>{{ $labelText }}</option>
                @endforeach
            </select>
            @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}acquisition_source" class="{{ $label }}">Source d'acquisition</label>
            <select name="acquisition_source" id="{{ $idPrefix }}acquisition_source" class="{{ $input }} @error('acquisition_source') border-red-500 @enderror">
                <option value="">Sélectionner</option>
                @foreach(\App\Models\Client::ACQUISITION_SOURCES as $key => $labelText)
                    <option value="{{ $key }}" {{ $val('acquisition_source') == $key ? 'selected' : '' }}>{{ $labelText }}</option>
                @endforeach
            </select>
            @error('acquisition_source')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}discount_percent" class="{{ $label }}">Remise permanente (%)</label>
            <input type="number" step="0.01" min="0" max="100" name="discount_percent" id="{{ $idPrefix }}discount_percent"
                   value="{{ $val('discount_percent') }}" placeholder="Ex : 5"
                   class="{{ $input }} @error('discount_percent') border-red-500 @enderror">
            @error('discount_percent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}loyalty_points" class="{{ $label }}">Points de fidélité</label>
            <input type="number" min="0" name="loyalty_points" id="{{ $idPrefix }}loyalty_points"
                   value="{{ $val('loyalty_points', 0) }}"
                   class="{{ $input }} @error('loyalty_points') border-red-500 @enderror">
            @error('loyalty_points')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

{{-- 7. Préférences --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
        </span>
        <div>
            <h2 class="text-base font-semibold text-gray-900">Préférences d'achat</h2>
            <p class="text-xs text-gray-500">Paiement, livraison et plafonds</p>
        </div>
    </div>
    <div class="p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <label for="{{ $idPrefix }}preferred_payment_method" class="{{ $label }}">Moyen de paiement habituel</label>
            <select name="preferred_payment_method" id="{{ $idPrefix }}preferred_payment_method" class="{{ $input }} @error('preferred_payment_method') border-red-500 @enderror">
                <option value="">Sélectionner</option>
                @foreach(\App\Models\Client::PAYMENT_METHODS as $key => $labelText)
                    <option value="{{ $key }}" {{ $val('preferred_payment_method') == $key ? 'selected' : '' }}>{{ $labelText }}</option>
                @endforeach
            </select>
            @error('preferred_payment_method')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}preferred_delivery_mode" class="{{ $label }}">Mode de livraison préféré</label>
            <select name="preferred_delivery_mode" id="{{ $idPrefix }}preferred_delivery_mode" class="{{ $input }} @error('preferred_delivery_mode') border-red-500 @enderror">
                <option value="">Sélectionner</option>
                @foreach(\App\Models\Client::DELIVERY_MODES as $key => $labelText)
                    <option value="{{ $key }}" {{ $val('preferred_delivery_mode') == $key ? 'selected' : '' }}>{{ $labelText }}</option>
                @endforeach
            </select>
            @error('preferred_delivery_mode')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}currency" class="{{ $label }}">Devise</label>
            <select name="currency" id="{{ $idPrefix }}currency" class="{{ $input }} @error('currency') border-red-500 @enderror">
                <option value="MAD" {{ $val('currency', 'MAD') == 'MAD' ? 'selected' : '' }}>MAD</option>
                <option value="EUR" {{ $val('currency') == 'EUR' ? 'selected' : '' }}>EUR</option>
                <option value="USD" {{ $val('currency') == 'USD' ? 'selected' : '' }}>USD</option>
            </select>
            @error('currency')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}purchase_frequency" class="{{ $label }}">Fréquence d'achat</label>
            <select name="purchase_frequency" id="{{ $idPrefix }}purchase_frequency" class="{{ $input }} @error('purchase_frequency') border-red-500 @enderror">
                <option value="">Sélectionner</option>
                @foreach(\App\Models\Client::PURCHASE_FREQUENCIES as $key => $labelText)
                    <option value="{{ $key }}" {{ $val('purchase_frequency') == $key ? 'selected' : '' }}>{{ $labelText }}</option>
                @endforeach
            </select>
            @error('purchase_frequency')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $idPrefix }}order_ceiling" class="{{ $label }}">Plafond de commande <span class="text-gray-400 font-normal">(optionnel)</span></label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-medium text-gray-500">MAD</span>
                <input type="number" step="0.01" min="0" name="order_ceiling" id="{{ $idPrefix }}order_ceiling"
                       value="{{ $val('order_ceiling') }}" placeholder="Ex : 10000"
                       class="{{ $input }} pl-12 @error('order_ceiling') border-red-500 @enderror">
            </div>
            @error('order_ceiling')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

{{-- 8. Documents --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
         x-data="{ files: [] }"
         @dragover.prevent
         @drop.prevent="files = [...$event.dataTransfer.files]; $refs.fileInput.files = $event.dataTransfer.files">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50 text-teal-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
        </span>
        <div>
            <h2 class="text-base font-semibold text-gray-900">Documents joints <span class="text-gray-400 font-normal text-sm">(optionnel)</span></h2>
            <p class="text-xs text-gray-500">CIN, contrats, justificatifs — PDF, JPG, PNG (max 5 Mo)</p>
        </div>
    </div>
    <div class="p-5 space-y-4">
        @if($client && $client->documents->isNotEmpty())
            <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                @foreach($client->documents as $document)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 bg-white hover:bg-gray-50">
                        <a href="{{ $document->url() }}" target="_blank" class="text-sm text-blue-600 hover:underline truncate">
                            {{ $document->original_name }}
                        </a>
                        <form action="{{ route('clients.documents.destroy', [$client, $document]) }}" method="POST" onsubmit="return confirm('Supprimer ce document ?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800">Supprimer</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <label class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 rounded-xl px-6 py-10 cursor-pointer hover:border-[#fdb819] hover:bg-[#fdb819]/5 transition">
            <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            <span class="text-sm font-medium text-gray-700">Glisser-déposer vos fichiers</span>
            <span class="text-xs text-gray-500">ou cliquer pour parcourir</span>
            <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png" x-ref="fileInput"
                   class="sr-only" @change="files = [...$event.target.files]">
        </label>
        <ul x-show="files.length" class="text-sm text-gray-600 space-y-1">
            <template x-for="(file, i) in files" :key="i">
                <li class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
                    <span x-text="file.name"></span>
                </li>
            </template>
        </ul>
        @error('documents')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @error('documents.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</section>
@endunless
