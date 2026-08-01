@extends('layouts.app')

@section('title', "Connexion — LAV'FAST")

@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
    .auth-shell {
        --blue: #0a5d8a;
        --blue-deep: #074866;
        --gold: #fdb819;
        font-family: 'Manrope', sans-serif;
        min-height: 100vh;
        display: grid;
        place-items: center;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
        background: linear-gradient(145deg, #042a40 0%, #0a5d8a 45%, #086088 100%);
    }

    .auth-bg {
        position: absolute;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
    }

    .auth-bg__mesh {
        position: absolute;
        inset: -20%;
        background:
            radial-gradient(circle at 20% 30%, rgba(253, 184, 25, 0.35), transparent 32%),
            radial-gradient(circle at 80% 20%, rgba(56, 189, 248, 0.28), transparent 34%),
            radial-gradient(circle at 70% 75%, rgba(253, 184, 25, 0.22), transparent 36%),
            radial-gradient(circle at 25% 80%, rgba(14, 165, 233, 0.25), transparent 30%);
        animation: auth-mesh 16s ease-in-out infinite alternate;
        filter: blur(8px);
    }

    .auth-bg__grid {
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
        background-size: 56px 56px;
        mask-image: radial-gradient(ellipse 70% 60% at 50% 45%, black, transparent);
        animation: auth-grid 28s linear infinite;
    }

    .auth-blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(48px);
        opacity: 0.7;
        will-change: transform;
    }

    .auth-blob--1 {
        width: 28rem;
        height: 28rem;
        background: #fdb819;
        top: -8rem;
        left: -6rem;
        animation: auth-float-a 14s ease-in-out infinite;
    }

    .auth-blob--2 {
        width: 22rem;
        height: 22rem;
        background: #38bdf8;
        top: 20%;
        right: -7rem;
        animation: auth-float-b 18s ease-in-out infinite;
    }

    .auth-blob--3 {
        width: 24rem;
        height: 24rem;
        background: #f59e0b;
        bottom: -10rem;
        left: 35%;
        animation: auth-float-c 16s ease-in-out infinite;
    }

    .auth-blob--4 {
        width: 16rem;
        height: 16rem;
        background: #0ea5e9;
        bottom: 15%;
        left: -4rem;
        animation: auth-float-a 20s ease-in-out infinite reverse;
    }

    .auth-spark {
        position: absolute;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 0 12px rgba(253, 184, 25, 0.9);
        opacity: 0;
        animation: auth-spark 5.5s ease-in-out infinite;
    }

    .auth-spark:nth-child(1) { top: 18%; left: 12%; animation-delay: 0s; }
    .auth-spark:nth-child(2) { top: 28%; left: 78%; animation-delay: 1.1s; background: #fdb819; }
    .auth-spark:nth-child(3) { top: 62%; left: 18%; animation-delay: 2.2s; }
    .auth-spark:nth-child(4) { top: 72%; left: 88%; animation-delay: 0.7s; background: #fdb819; }
    .auth-spark:nth-child(5) { top: 42%; left: 92%; animation-delay: 3s; }
    .auth-spark:nth-child(6) { top: 84%; left: 48%; animation-delay: 1.8s; background: #fdb819; }
    .auth-spark:nth-child(7) { top: 12%; left: 52%; animation-delay: 2.6s; }
    .auth-spark:nth-child(8) { top: 55%; left: 8%; animation-delay: 3.8s; }

    .auth-ring {
        position: absolute;
        border: 1.5px solid rgba(255, 255, 255, 0.12);
        border-radius: 50%;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        animation: auth-ring 22s linear infinite;
    }

    .auth-ring--lg {
        width: min(90vw, 720px);
        height: min(90vw, 720px);
    }

    .auth-ring--md {
        width: min(70vw, 520px);
        height: min(70vw, 520px);
        animation-direction: reverse;
        animation-duration: 30s;
        border-color: rgba(253, 184, 25, 0.18);
    }

    .auth-panel {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 420px;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 1.5rem;
        padding: 2.25rem 2rem 2rem;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.8) inset,
            0 28px 60px rgba(2, 24, 38, 0.35);
        animation: auth-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .auth-mark {
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 1rem;
        background: var(--gold);
        display: grid;
        place-items: center;
        margin: 0 auto 1.25rem;
        box-shadow: 0 10px 24px rgba(253, 184, 25, 0.35);
        animation: auth-pop 0.65s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .auth-title {
        text-align: center;
        font-weight: 800;
        font-size: 1.75rem;
        letter-spacing: -0.03em;
        color: var(--blue);
        line-height: 1.15;
    }

    .auth-sub {
        text-align: center;
        margin-top: 0.4rem;
        color: #64748b;
        font-size: 0.925rem;
        font-weight: 500;
    }

    .auth-field {
        margin-top: 1.1rem;
    }

    .auth-field label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #334155;
        margin-bottom: 0.4rem;
    }

    .auth-field input[type="email"],
    .auth-field input[type="password"] {
        width: 100%;
        border: 1.5px solid #dbe3ea;
        background: #f8fafc;
        border-radius: 0.85rem;
        padding: 0.85rem 1rem;
        font-size: 0.95rem;
        color: #0f172a;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }

    .auth-field input:focus {
        outline: none;
        background: #fff;
        border-color: var(--blue);
        box-shadow: 0 0 0 4px rgba(10, 93, 138, 0.12);
    }

    .auth-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 1rem;
        font-size: 0.875rem;
        color: #475569;
        font-weight: 500;
    }

    .auth-row input {
        width: 1rem;
        height: 1rem;
        accent-color: var(--blue);
    }

    .auth-btn {
        margin-top: 1.35rem;
        width: 100%;
        border: 0;
        border-radius: 0.85rem;
        padding: 0.95rem 1rem;
        font-weight: 700;
        font-size: 0.95rem;
        color: #fff;
        background: var(--blue);
        cursor: pointer;
        transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    }

    .auth-btn:hover {
        background: var(--blue-deep);
        box-shadow: 0 12px 28px rgba(10, 93, 138, 0.25);
        transform: translateY(-1px);
    }

    .auth-btn:active {
        transform: translateY(0);
    }

    .auth-alert {
        margin-top: 1.25rem;
        border-radius: 0.85rem;
        padding: 0.75rem 0.9rem;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .auth-alert--ok {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }

    .auth-alert--err {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .auth-foot {
        margin-top: 1.5rem;
        text-align: center;
        font-size: 0.75rem;
        color: #94a3b8;
        font-weight: 500;
    }

    .auth-creds {
        margin-top: 1.25rem;
        padding: 0.85rem 1rem;
        border-radius: 0.85rem;
        background: #f1f5f9;
        border: 1px dashed #cbd5e1;
        text-align: center;
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
        line-height: 1.55;
    }

    .auth-creds strong {
        color: #0a5d8a;
        font-weight: 700;
    }

    .auth-gold-line {
        width: 3rem;
        height: 3px;
        border-radius: 999px;
        background: var(--gold);
        margin: 1rem auto 0;
        animation: auth-line 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
    }

    @keyframes auth-in {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes auth-pop {
        from { opacity: 0; transform: scale(0.85); }
        to { opacity: 1; transform: scale(1); }
    }

    @keyframes auth-line {
        from { opacity: 0; transform: scaleX(0.4); }
        to { opacity: 1; transform: scaleX(1); }
    }

    @keyframes auth-mesh {
        from { transform: translate(0, 0) scale(1) rotate(0deg); }
        to { transform: translate(-3%, 2%) scale(1.08) rotate(4deg); }
    }

    @keyframes auth-grid {
        from { transform: translate(0, 0); }
        to { transform: translate(56px, 56px); }
    }

    @keyframes auth-float-a {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(40px, 50px) scale(1.12); }
    }

    @keyframes auth-float-b {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(-50px, 35px) scale(1.15); }
    }

    @keyframes auth-float-c {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(30px, -45px) scale(1.1); }
    }

    @keyframes auth-spark {
        0%, 100% { opacity: 0; transform: scale(0.4) translateY(0); }
        40% { opacity: 0.95; transform: scale(1) translateY(-10px); }
        70% { opacity: 0.4; transform: scale(0.7) translateY(-18px); }
    }

    @keyframes auth-ring {
        from { transform: translate(-50%, -50%) rotate(0deg); }
        to { transform: translate(-50%, -50%) rotate(360deg); }
    }

    @media (prefers-reduced-motion: reduce) {
        .auth-bg__mesh,
        .auth-bg__grid,
        .auth-blob,
        .auth-spark,
        .auth-ring {
            animation: none;
        }
    }
</style>

<div class="auth-shell">
    <div class="auth-bg" aria-hidden="true">
        <div class="auth-bg__mesh"></div>
        <div class="auth-blob auth-blob--1"></div>
        <div class="auth-blob auth-blob--2"></div>
        <div class="auth-blob auth-blob--3"></div>
        <div class="auth-blob auth-blob--4"></div>
        <div class="auth-bg__grid"></div>
        <div class="auth-ring auth-ring--lg"></div>
        <div class="auth-ring auth-ring--md"></div>
        <span class="auth-spark"></span>
        <span class="auth-spark"></span>
        <span class="auth-spark"></span>
        <span class="auth-spark"></span>
        <span class="auth-spark"></span>
        <span class="auth-spark"></span>
        <span class="auth-spark"></span>
        <span class="auth-spark"></span>
    </div>

    <div class="auth-panel">
        <div class="auth-mark" aria-hidden="true">
            <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
        </div>

        <h1 class="auth-title">LAV'FAST</h1>
        <div class="auth-gold-line" aria-hidden="true"></div>
        <p class="auth-sub">Connexion</p>

        @if (session('success'))
            <div class="auth-alert auth-alert--ok" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="auth-alert auth-alert--err" role="alert">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('login.post') }}" method="POST">
            @csrf

            <div class="auth-field">
                <label for="email">E-mail</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                    value="{{ old('email') }}"
                    placeholder="superadmin@achraf.com"
                >
            </div>

            <div class="auth-field">
                <label for="password">Mot de passe</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    placeholder="••••••••"
                >
            </div>

            <label class="auth-row">
                <input id="remember" name="remember" type="checkbox">
                <span>Se souvenir de moi</span>
            </label>

            <button type="submit" class="auth-btn">Se connecter</button>
        </form>

        <div class="auth-creds">
            Identifiants par défaut :<br>
            <strong>superadmin@achraf.com</strong> / <strong>password</strong>
        </div>

        <p class="auth-foot">© {{ date('Y') }} LAV'FAST</p>
    </div>
</div>
@endsection
