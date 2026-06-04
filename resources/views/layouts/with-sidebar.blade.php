@extends('layouts.app')

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
    }
    @media (min-width: 1024px) {
        .app-shell-aside {
            transform: translateX(0);
        }
    }
    .app-shell-aside.is-open {
        transform: translateX(0);
    }
    html.pos-full-view-active .app-shell-aside {
        display: none !important;
    }
    html.pos-full-view-active .app-shell-main {
        margin-left: 0 !important;
    }
</style>
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
        class="app-shell-aside fixed inset-y-0 left-0 z-40 bg-white shadow-lg border-r border-gray-100 flex flex-col"
        :class="{ 'is-open': sidebarOpen }"
        @click="if ($event.target.closest('a[href]')) sidebarOpen = false"
    >
        @include('layouts.sidebar')
    </aside>

    <div class="app-shell-main flex-1 flex flex-col w-full min-w-0">
        @hasSection('hide_shell_header')
        @else
        <header class="sticky top-0 z-20 flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-200 shadow-sm">
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
            <span class="font-semibold text-gray-900 truncate">@yield('sidebar_page_title', 'hsabati')</span>
        </header>
        @endif
        @yield('main')
    </div>
</div>
@stack('scripts')
<style>
    tr.table-row-selected {
        background-color: #fffbeb !important;
    }
    tr.table-row-selected td {
        background-color: #fffbeb !important;
    }
</style>
<script>window.tableBulkExportUrl = @json(route('table.export'));</script>
@php
    $tableBulkSelectionScript = public_path('js/table-bulk-selection.js');
@endphp
@if (is_readable($tableBulkSelectionScript))
<script>{!! file_get_contents($tableBulkSelectionScript) !!}</script>
@else
<script src="{{ asset('js/table-bulk-selection.js') }}?v=4"></script>
@endif
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
