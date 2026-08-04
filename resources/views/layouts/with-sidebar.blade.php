@extends(\App\Support\SoftNavigation::wants(request()) ? 'layouts.soft-nav-frame' : 'layouts.app')

@if (! \App\Support\SoftNavigation::wants(request()))
@section('content')
<script>
    (function () {
        try {
            document.documentElement.classList.toggle(
                'sidebar-collapsed',
                localStorage.getItem('sidebarCollapsed') === 'true'
            );
        } catch (e) {}
    })();
</script>
<style>
    @media (min-width: 1024px) {
        .app-shell-main { margin-left: 16rem; }
        html.sidebar-collapsed .app-shell-main { margin-left: 5rem; }
        .app-shell-aside { width: 16rem; }
        html.sidebar-collapsed .app-shell-aside { width: 5rem; }
    }
    .app-shell-aside {
        transform: translateX(-100%);
        transition: transform 220ms ease, width 220ms ease, box-shadow 220ms ease;
        box-shadow: 8px 0 28px -16px rgba(5, 58, 82, 0.55);
        border-right: 0;
    }
    @media (min-width: 1024px) {
        .app-shell-aside {
            transform: translateX(0);
        }
    }
    .app-shell-aside.is-open {
        transform: translateX(0);
        box-shadow: 16px 0 40px -12px rgba(5, 58, 82, 0.45);
    }
    html.pos-full-view-active .app-shell-aside {
        display: none !important;
    }
    html.pos-full-view-active .app-shell-main {
        margin-left: 0 !important;
    }
    .module-tabs-scroll {
        scrollbar-width: thin;
        scrollbar-color: #d1d5db transparent;
        -webkit-overflow-scrolling: touch;
    }
    .module-tabs-scroll::-webkit-scrollbar {
        height: 4px;
    }
    .module-tabs-scroll::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 9999px;
    }
    .soft-nav-loading {
        position: absolute;
        inset: 0;
        z-index: 30;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 8rem;
        background: rgba(249, 250, 251, 0.72);
        backdrop-filter: blur(1px);
    }
    .soft-nav-loading[hidden] {
        display: none !important;
    }
    .soft-nav-loading__card {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1.25rem;
        border-radius: 0.75rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.08);
        color: #374151;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .soft-nav-loading__spinner {
        width: 1.25rem;
        height: 1.25rem;
        color: #0a5d8a;
        animation: soft-nav-spin 0.75s linear infinite;
    }
    .soft-nav-loading__track {
        opacity: 0.25;
    }
    .soft-nav-loading__arc {
        opacity: 0.9;
    }
    @keyframes soft-nav-spin {
        to { transform: rotate(360deg); }
    }
</style>
@php
    $navigationModules = \App\Support\Navigation::modules(Auth::user());
    $activeNavigationModule = \App\Support\Navigation::activeModule($navigationModules, request());
    $moduleTabs = $activeNavigationModule['children'] ?? [];
@endphp
<div
    class="min-h-screen bg-gray-50 flex relative"
    x-data="{
        sidebarOpen: false,
        sidebarCollapsed: document.documentElement.classList.contains('sidebar-collapsed'),
        toggleCollapsed() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            document.documentElement.classList.toggle('sidebar-collapsed', this.sidebarCollapsed);
            localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
        }
    }"
    @keydown.escape.window="sidebarOpen = false"
>
    <div
        x-show="sidebarOpen"
        @click="sidebarOpen = false"
        class="fixed inset-0 bg-gray-900/40 z-30 lg:hidden"
        style="display: none;"
    ></div>

    <aside
        class="app-shell-aside fixed inset-y-0 left-0 z-40 flex flex-col overflow-hidden"
        :class="{ 'is-open': sidebarOpen }"
        @click="if ($event.target.closest('a[href]')) sidebarOpen = false"
    >
        @include('layouts.sidebar')
    </aside>

    <div class="app-shell-main flex-1 flex flex-col w-full min-w-0 relative">
        @hasSection('hide_shell_header')
        @else
        <div class="sticky top-0 z-20 bg-white shadow-sm">
            <header class="flex items-center gap-3 px-4 py-3 border-b {{ $moduleTabs !== [] ? 'border-gray-100' : 'border-gray-200' }}">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 -ml-1 touch-manipulation lg:hidden"
                    aria-label="Ouvrir le menu"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="toggleCollapsed()"
                    class="hidden lg:flex p-2 rounded-lg text-gray-600 hover:bg-gray-100 -ml-1 touch-manipulation"
                    aria-label="Réduire le menu"
                >
                    <svg class="h-6 w-6" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>
                <span id="app-page-title" class="font-semibold text-gray-900 truncate">@yield('sidebar_page_title', 'hsabati')</span>
            </header>

            <div id="app-module-tabs" @if ($moduleTabs === []) hidden @endif>
                <!--soft-nav:tabs:start-->
                @if ($moduleTabs !== [])
                    <nav class="module-tabs-scroll overflow-x-auto border-b border-gray-200 bg-white" aria-label="Menus de {{ $activeNavigationModule['label'] ?? '' }}">
                        <div class="flex min-w-max items-center gap-1 px-4">
                            @foreach ($moduleTabs as $tab)
                                @php
                                    $tabActive = \App\Support\Navigation::isActive($tab, request());
                                @endphp
                                <a
                                    href="{{ route($tab['route']) }}"
                                    data-soft-nav
                                    class="relative inline-flex min-h-12 items-center px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors {{ $tabActive ? 'text-[#0a5d8a]' : 'text-gray-500 hover:text-gray-900' }}"
                                    @if($tabActive) aria-current="page" @endif
                                >
                                    {{ $tab['label'] }}
                                    @if($tabActive)
                                        <span class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-[#fdb819]"></span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </nav>
                @endif
                <!--soft-nav:tabs:end-->
            </div>
        </div>
        @endif
        @php
            $softNavScript = public_path('js/soft-nav.js');
            $softNavVersion = is_readable($softNavScript) ? filemtime($softNavScript) : time();
        @endphp
        @if (is_readable($softNavScript))
        <script>/* soft-nav v={{ $softNavVersion }} */{!! file_get_contents($softNavScript) !!}</script>
        @else
        <script src="{{ asset('js/soft-nav.js') }}?v={{ $softNavVersion }}"></script>
        @endif
        <div id="app-page-root">
            <!--soft-nav:page:start-->
            @yield('main')
            @stack('scripts')
            <!--soft-nav:page:end-->
        </div>
        <div
            id="soft-nav-loading"
            class="soft-nav-loading"
            hidden
            aria-live="polite"
            aria-busy="true"
        >
            <div class="soft-nav-loading__card">
                <svg class="soft-nav-loading__spinner" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="soft-nav-loading__track" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="soft-nav-loading__arc" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span class="soft-nav-loading__label">Chargement…</span>
            </div>
        </div>
    </div>
</div>
<style>
    tr.table-row-selected {
        background-color: #fffbeb !important;
    }
    tr.table-row-selected td {
        background-color: #fffbeb !important;
    }
    .table-export-toast {
        width: min(24rem, calc(100vw - 2rem));
    }
    .party-select-field .select2-container {
        width: 100% !important;
        max-width: 100%;
    }
    .party-select-wrap .select2-container--default .select2-selection--single {
        min-height: 38px;
    }
</style>
<script>
    window.tableBulkExportUrl = @json(route('table.export'));
    window.tableBulkZipExportUrl = @json(route('table.export.zip'));
</script>
@php
    $tableBulkSelectionScript = public_path('js/table-bulk-selection.js');
    $tableBulkSelectionVersion = is_readable($tableBulkSelectionScript) ? filemtime($tableBulkSelectionScript) : time();
@endphp
@if (is_readable($tableBulkSelectionScript))
<script>/* table-bulk-selection v={{ $tableBulkSelectionVersion }} */{!! file_get_contents($tableBulkSelectionScript) !!}</script>
@else
<script src="{{ asset('js/table-bulk-selection.js') }}?v={{ $tableBulkSelectionVersion }}"></script>
@endif
@php
    $tablePaginationScript = public_path('js/table-pagination.js');
@endphp
@if (is_readable($tablePaginationScript))
<script>{!! file_get_contents($tablePaginationScript) !!}</script>
@else
<script src="{{ asset('js/table-pagination.js') }}?v=1"></script>
@endif
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
@endif
