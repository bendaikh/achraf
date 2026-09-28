@extends('layouts.app')

@section('title', 'Mot de passe oublié')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-slate-100 px-4">
    <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <h1 class="text-xl font-bold text-slate-900">Mot de passe oublié</h1>
        <p class="text-sm text-slate-500 mt-2">Saisissez votre e-mail. Si un compte existe, vous recevrez un lien de réinitialisation.</p>

        @if(session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                Une erreur est survenue. Réessayez plus tard.
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">E-mail</label>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="w-full px-4 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Envoyer le lien</button>
        </form>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('login') }}" class="text-[#0a5d8a] hover:underline">← Retour à la connexion</a>
        </p>
    </div>
</div>
@endsection
