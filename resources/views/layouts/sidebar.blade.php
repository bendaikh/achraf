@php
    $navigationModules = \App\Support\Navigation::modules(Auth::user());
@endphp

<style>
    .app-sidebar {
        --sb-bg-0: #0a5d8a;
        --sb-bg-1: #074866;
        --sb-bg-2: #053a52;
        --sb-accent: #fdb819;
        --sb-accent-soft: rgba(253, 184, 25, 0.18);
        --sb-text: rgba(255, 255, 255, 0.92);
        --sb-muted: rgba(255, 255, 255, 0.58);
        --sb-hover: rgba(255, 255, 255, 0.08);
        --sb-border: rgba(255, 255, 255, 0.1);
        position: relative;
        height: 100%;
        display: flex;
        flex-direction: column;
        color: var(--sb-text);
        background:
            radial-gradient(120% 80% at 0% 0%, rgba(253, 184, 25, 0.16) 0%, transparent 42%),
            radial-gradient(90% 60% at 100% 100%, rgba(255, 255, 255, 0.08) 0%, transparent 45%),
            linear-gradient(165deg, var(--sb-bg-0) 0%, var(--sb-bg-1) 48%, var(--sb-bg-2) 100%);
        overflow: hidden;
    }
    .app-sidebar::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        background-size: 28px 28px;
        mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.45), transparent 70%);
        pointer-events: none;
        opacity: 0.5;
    }
    .app-sidebar > * {
        position: relative;
        z-index: 1;
    }
    .app-sidebar__brand {
        flex-shrink: 0;
        padding: 1.15rem 1rem 1rem;
        border-bottom: 1px solid var(--sb-border);
    }
    .app-sidebar__brand-mark {
        height: 2.5rem;
        width: 2.5rem;
        border-radius: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: linear-gradient(145deg, #ffd056 0%, var(--sb-accent) 55%, #e5a40f 100%);
        color: #0a5d8a;
        box-shadow:
            0 8px 18px -10px rgba(0, 0, 0, 0.45),
            inset 0 1px 0 rgba(255, 255, 255, 0.45);
    }
    .app-sidebar__brand-title {
        font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
        font-size: 1.125rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        line-height: 1.1;
        color: #fff;
        white-space: nowrap;
    }
    .app-sidebar__brand-sub {
        margin-top: 0.15rem;
        font-size: 0.68rem;
        font-weight: 500;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--sb-muted);
        white-space: nowrap;
    }
    .app-sidebar__nav {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.85rem 0.65rem;
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.25) transparent;
    }
    .app-sidebar__nav::-webkit-scrollbar {
        width: 4px;
    }
    .app-sidebar__nav::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.22);
        border-radius: 9999px;
    }
    .sidebar-nav-link {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.25rem;
        padding: 0.7rem 0.85rem;
        border-radius: 0.85rem;
        color: var(--sb-muted);
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition:
            background-color 160ms ease,
            color 160ms ease,
            transform 160ms ease,
            box-shadow 160ms ease;
    }
    .sidebar-nav-link:hover {
        background: var(--sb-hover);
        color: #fff;
    }
    .sidebar-nav-link.is-active {
        background: linear-gradient(90deg, var(--sb-accent-soft), rgba(255, 255, 255, 0.06));
        color: #fff;
        box-shadow: inset 0 0 0 1px rgba(253, 184, 25, 0.28);
    }
    .sidebar-nav-link.is-active .sidebar-nav-link__icon {
        background: var(--sb-accent);
        color: #0a5d8a;
    }
    .sidebar-nav-link__marker {
        position: absolute;
        left: 0;
        top: 50%;
        width: 3px;
        height: 1.5rem;
        border-radius: 0 9999px 9999px 0;
        background: var(--sb-accent);
        transform: translateY(-50%);
    }
    .sidebar-nav-link__icon {
        width: 2rem;
        height: 2rem;
        border-radius: 0.65rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: rgba(255, 255, 255, 0.08);
        color: inherit;
        transition: background-color 160ms ease, color 160ms ease, box-shadow 160ms ease;
    }
    .sidebar-nav-link__icon svg {
        width: 1.05rem;
        height: 1.05rem;
    }
    .sidebar-nav-link__label {
        flex: 1;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar-nav-link__chevron {
        width: 0.9rem;
        height: 0.9rem;
        flex-shrink: 0;
        opacity: 0.55;
    }
    .sidebar-nav-link.is-active .sidebar-nav-link__chevron {
        opacity: 0.9;
        color: var(--sb-accent);
    }
    html.sidebar-collapsed .app-shell-aside:not(.is-open) .sidebar-nav-link {
        justify-content: center;
        padding-left: 0.55rem;
        padding-right: 0.55rem;
        gap: 0;
    }
    .app-sidebar__footer {
        flex-shrink: 0;
        padding: 0.9rem;
        border-top: 1px solid var(--sb-border);
        background: rgba(0, 0, 0, 0.12);
        backdrop-filter: blur(8px);
    }
    .app-sidebar__user-avatar {
        height: 2.5rem;
        width: 2.5rem;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.8rem;
        font-weight: 700;
        color: #0a5d8a;
        background: linear-gradient(145deg, #ffd056, var(--sb-accent));
        box-shadow: 0 6px 14px -8px rgba(0, 0, 0, 0.5);
    }
    .app-sidebar__logout {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding: 0.55rem 0.85rem;
        border-radius: 0.75rem;
        border: 1px solid var(--sb-border);
        background: rgba(255, 255, 255, 0.06);
        color: var(--sb-text);
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
    }
    .app-sidebar__logout:hover {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.22);
        color: #fff;
    }
    html.sidebar-collapsed .app-shell-aside:not(.is-open) .app-sidebar__logout {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
</style>

<div class="app-sidebar">
    <div class="app-sidebar__brand">
        <div class="flex items-center" :class="sidebarCollapsed && !sidebarOpen ? 'justify-center' : 'gap-3'">
            <div class="app-sidebar__brand-mark" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <div x-show="!sidebarCollapsed || sidebarOpen" x-transition.opacity.duration.150ms class="overflow-hidden min-w-0">
                <h1 class="app-sidebar__brand-title">LAV'FAST</h1>
                <p class="app-sidebar__brand-sub">E-commerce</p>
            </div>
        </div>
    </div>

    <nav class="app-sidebar__nav" aria-label="Navigation principale">
        @foreach ($navigationModules as $module)
            @php
                $moduleActive = \App\Support\Navigation::isActive($module, request());
            @endphp
            <a
                href="{{ route($module['route']) }}"
                class="sidebar-nav-link {{ $moduleActive ? 'is-active' : '' }}"
                title="{{ $module['label'] }}"
                data-nav-module="{{ $module['key'] ?? $module['route'] }}"
                @if(($module['soft_nav'] ?? true) !== false) data-soft-nav @endif
                @if($moduleActive) aria-current="page" @endif
            >
                <span
                    data-nav-active-marker
                    class="sidebar-nav-link__marker"
                    x-show="!sidebarCollapsed || sidebarOpen"
                    @if(!$moduleActive) style="display: none;" @endif
                ></span>
                <span class="sidebar-nav-link__icon" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @foreach ($module['icon'] as $path)
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.85" d="{{ $path }}"></path>
                        @endforeach
                    </svg>
                </span>
                <span class="sidebar-nav-link__label" x-show="!sidebarCollapsed || sidebarOpen" x-transition.opacity.duration.150ms>
                    {{ $module['label'] }}
                </span>
                @if(!empty($module['children']))
                    <svg
                        class="sidebar-nav-link__chevron"
                        x-show="!sidebarCollapsed || sidebarOpen"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="app-sidebar__footer">
        <div class="flex items-center" :class="sidebarCollapsed && !sidebarOpen ? 'justify-center' : 'gap-3'">
            <div class="app-sidebar__user-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 2)) }}</div>
            <div class="flex-1 min-w-0" x-show="!sidebarCollapsed || sidebarOpen" x-transition.opacity.duration.150ms>
                <p class="text-sm font-semibold text-white truncate leading-tight">{{ Auth::user()->name }}</p>
                <p class="text-xs truncate mt-0.5" style="color: var(--sb-muted);">{{ Auth::user()->email }}</p>
            </div>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="app-sidebar__logout" title="Déconnexion">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span x-show="!sidebarCollapsed || sidebarOpen" x-transition.opacity.duration.150ms>Déconnexion</span>
            </button>
        </form>
    </div>
</div>
