@extends('layouts.app')

@section('title', 'Nouveau mot de passe')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-slate-100 px-4">
    <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <h1 class="text-xl font-bold text-slate-900">Nouveau mot de passe</h1>
        <p class="text-sm text-slate-500 mt-2">Choisissez un mot de passe d’au moins 8 caractères.</p>

        @if($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">E-mail</label>
                <input type="email" name="email" value="{{ old('email', $email) }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nouveau mot de passe</label>
                <input type="password" name="password" required minlength="8" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Confirmation</label>
                <input type="password" name="password_confirmation" required minlength="8" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="w-full px-4 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Enregistrer</button>
        </form>
    </div>
</div>
@endsection
