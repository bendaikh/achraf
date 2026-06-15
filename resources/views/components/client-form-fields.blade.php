@props(['clientCode' => '', 'idPrefix' => ''])

<div class="border-b border-gray-200 pb-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Type de client</h2>
    <div class="flex flex-col sm:flex-row gap-4">
        <label class="flex items-center gap-2 p-4 border rounded-lg cursor-pointer transition flex-1" :class="clientType === 'entreprise' ? 'border-[#fdb819] bg-[#fdb819]/10' : 'border-gray-200 hover:border-gray-300'">
            <input type="radio" name="client_type" value="entreprise" x-model="clientType" class="text-[#fdb819] focus:ring-[#fdb819]">
            <div>
                <span class="font-medium text-gray-900">Entreprise</span>
                <p class="text-xs text-gray-500">Client professionnel / société</p>
            </div>
        </label>
        <label class="flex items-center gap-2 p-4 border rounded-lg cursor-pointer transition flex-1" :class="clientType === 'particulier' ? 'border-[#fdb819] bg-[#fdb819]/10' : 'border-gray-200 hover:border-gray-300'">
            <input type="radio" name="client_type" value="particulier" x-model="clientType" class="text-[#fdb819] focus:ring-[#fdb819]">
            <div>
                <span class="font-medium text-gray-900">Particulier</span>
                <p class="text-xs text-gray-500">Client individuel</p>
            </div>
        </label>
    </div>
</div>

<div>
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations Générales</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div x-show="clientType === 'entreprise'">
            <label for="{{ $idPrefix }}name" class="block text-sm font-medium text-gray-700 mb-1">
                Raison sociale <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                name="name"
                id="{{ $idPrefix }}name"
                value="{{ old('name') }}"
                x-bind:required="clientType === 'entreprise'"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('name') border-red-500 @enderror"
            >
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div x-show="clientType === 'particulier'" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="{{ $idPrefix }}first_name" class="block text-sm font-medium text-gray-700 mb-1">
                    Prénom <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="first_name"
                    id="{{ $idPrefix }}first_name"
                    value="{{ old('first_name') }}"
                    x-bind:required="clientType === 'particulier'"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('first_name') border-red-500 @enderror"
                >
                @error('first_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="{{ $idPrefix }}last_name" class="block text-sm font-medium text-gray-700 mb-1">
                    Nom <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="last_name"
                    id="{{ $idPrefix }}last_name"
                    value="{{ old('last_name') }}"
                    x-bind:required="clientType === 'particulier'"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('last_name') border-red-500 @enderror"
                >
                @error('last_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="{{ $idPrefix }}phone" class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
            <input
                type="text"
                name="phone"
                id="{{ $idPrefix }}phone"
                value="{{ old('phone') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('phone') border-red-500 @enderror"
            >
            @error('phone')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $idPrefix }}email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
                type="email"
                name="email"
                id="{{ $idPrefix }}email"
                value="{{ old('email') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('email') border-red-500 @enderror"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $idPrefix }}address" class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
            <input
                type="text"
                name="address"
                id="{{ $idPrefix }}address"
                value="{{ old('address') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('address') border-red-500 @enderror"
            >
            @error('address')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $idPrefix }}code" class="block text-sm font-medium text-gray-700 mb-1">Code client</label>
            <input
                type="text"
                name="code"
                id="{{ $idPrefix }}code"
                value="{{ old('code', $clientCode) }}"
                readonly
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 @error('code') border-red-500 @enderror"
                placeholder="Généré automatiquement"
            >
            @error('code')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $idPrefix }}postal_code" class="block text-sm font-medium text-gray-700 mb-1">Code postal</label>
            <input
                type="text"
                name="postal_code"
                id="{{ $idPrefix }}postal_code"
                value="{{ old('postal_code') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('postal_code') border-red-500 @enderror"
            >
            @error('postal_code')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div x-show="clientType === 'entreprise'">
            <label for="{{ $idPrefix }}ice" class="block text-sm font-medium text-gray-700 mb-1">ICE</label>
            <input
                type="text"
                name="ice"
                id="{{ $idPrefix }}ice"
                value="{{ old('ice') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('ice') border-red-500 @enderror"
            >
            @error('ice')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $idPrefix }}region" class="block text-sm font-medium text-gray-700 mb-1">Région</label>
            <input
                type="text"
                name="region"
                id="{{ $idPrefix }}region"
                value="{{ old('region') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('region') border-red-500 @enderror"
            >
            @error('region')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div x-show="clientType === 'entreprise'">
            <label for="{{ $idPrefix }}fiscal_identifier" class="block text-sm font-medium text-gray-700 mb-1">Identifiant fiscal (IF)</label>
            <input
                type="text"
                name="fiscal_identifier"
                id="{{ $idPrefix }}fiscal_identifier"
                value="{{ old('fiscal_identifier') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('fiscal_identifier') border-red-500 @enderror"
            >
            @error('fiscal_identifier')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $idPrefix }}ville" class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
            <input
                type="text"
                name="ville"
                id="{{ $idPrefix }}ville"
                value="{{ old('ville') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('ville') border-red-500 @enderror"
            >
            @error('ville')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $idPrefix }}latitude" class="block text-sm font-medium text-gray-700 mb-1">Latitude (Google Maps)</label>
            <input
                type="text"
                name="latitude"
                id="{{ $idPrefix }}latitude"
                value="{{ old('latitude') }}"
                placeholder="33.5731"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('latitude') border-red-500 @enderror"
            >
            @error('latitude')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $idPrefix }}country" class="block text-sm font-medium text-gray-700 mb-1">Pays</label>
            <select
                name="country"
                id="{{ $idPrefix }}country"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('country') border-red-500 @enderror"
            >
                <option value="Maroc" {{ old('country', 'Maroc') == 'Maroc' ? 'selected' : '' }}>Maroc</option>
            </select>
            @error('country')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="{{ $idPrefix }}longitude" class="block text-sm font-medium text-gray-700 mb-1">Longitude (Google Maps)</label>
            <input
                type="text"
                name="longitude"
                id="{{ $idPrefix }}longitude"
                value="{{ old('longitude') }}"
                placeholder="-7.5898"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] focus:border-transparent @error('longitude') border-red-500 @enderror"
            >
            @error('longitude')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
