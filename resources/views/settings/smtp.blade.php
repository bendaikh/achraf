@extends('layouts.with-sidebar')

@section('title', 'E-mail / SMTP — Paramètres')
@section('sidebar_page_title', 'Paramètres')

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-6 lg:p-8 max-w-3xl mx-auto pb-16">
        @include('settings.partials.alerts')

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-start gap-3 px-6 py-5 border-b border-gray-100">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">E-mail / SMTP</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Configuration centrale unique (mot de passe oublié, documents, notifications).</p>
                </div>
                @php
                    $status = $smtp['mail_last_test_status'] ?? '';
                @endphp
                <span class="ml-auto inline-flex px-2.5 py-1 rounded-full text-xs font-medium
                    {{ $status === 'connected' ? 'bg-emerald-100 text-emerald-800' : ($status === 'error' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-600') }}">
                    {{ $status === 'connected' ? 'Connecté' : ($status === 'error' ? 'Erreur' : 'Non testé') }}
                </span>
            </div>

            <form method="POST" action="{{ route('settings.update') }}" class="p-6 space-y-5">
                @csrf
                @method('PUT')
                <input type="hidden" name="settings_type" value="smtp">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom expéditeur</label>
                        <input type="text" name="mail_from_name" value="{{ old('mail_from_name', $smtp['mail_from_name']) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Adresse e-mail professionnelle *</label>
                        <input type="email" name="mail_from_address" value="{{ old('mail_from_address', $smtp['mail_from_address']) }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Serveur SMTP *</label>
                        <input type="text" name="mail_host" value="{{ old('mail_host', $smtp['mail_host']) }}" required placeholder="smtp.exemple.com" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                        <input type="number" name="mail_port" value="{{ old('mail_port', $smtp['mail_port']) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Chiffrement</label>
                        <select name="mail_encryption" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                            @foreach(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Aucun'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('mail_encryption', $smtp['mail_encryption']) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Identifiant SMTP</label>
                        <input type="text" name="mail_username" value="{{ old('mail_username', $smtp['mail_username']) }}" autocomplete="off" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe SMTP</label>
                        <input type="password" name="mail_password" value="" autocomplete="new-password" placeholder="{{ $smtp['mail_password_set'] ? '•••••••• (laisser vide pour conserver)' : '' }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <p class="text-xs text-gray-500 mt-1">Stocké chiffré. Ne jamais hardcoder Gmail dans le code.</p>
                    </div>
                </div>

                @if(!empty($smtp['mail_last_test_message']))
                    <p class="text-xs text-slate-500">Dernier test : {{ $smtp['mail_last_test_at'] ?? '—' }} — {{ $smtp['mail_last_test_message'] }}</p>
                @endif

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="px-5 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Enregistrer</button>
                </div>
            </form>

            <div class="border-t border-gray-100 px-6 py-5 bg-slate-50">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Tester l’envoi</h2>
                <form method="POST" action="{{ route('settings.smtp.test') }}" class="flex flex-wrap gap-2 items-end">
                    @csrf
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs text-slate-500 mb-1">Destinataire test</label>
                        <input type="email" name="test_email" value="{{ old('test_email', auth()->user()?->email) }}" required class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                    </div>
                    <button type="submit" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium bg-white hover:bg-slate-50">Envoyer un test</button>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection
