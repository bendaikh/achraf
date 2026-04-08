@extends('layouts.app')

@section('content')
<div
    class="min-h-screen bg-gray-50 flex relative"
    x-data="{ 
        sidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true'
    }"
    @keydown.escape.window="sidebarOpen = false"
>
    <div
        x-show="sidebarOpen"
        x-transition.opacity.duration.200ms
        @click="sidebarOpen = false"
        class="fixed inset-0 bg-gray-900/40 z-30 lg:hidden"
        style="display: none;"
        x-cloak
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 max-w-[min(16rem,88vw)] bg-white shadow-lg border-r border-gray-100 flex flex-col transform transition-transform duration-200 ease-out"
        :class="{
            'translate-x-0': sidebarOpen,
            '-translate-x-full': !sidebarOpen,
            'lg:translate-x-0': !sidebarCollapsed,
            'lg:-translate-x-full': sidebarCollapsed
        }"
        @click="if ($event.target.closest('a[href]')) sidebarOpen = false"
    >
        @include('layouts.sidebar')
    </aside>

    <div class="flex-1 flex flex-col w-full min-w-0 transition-all duration-200" :class="sidebarCollapsed ? 'lg:ml-0' : 'lg:ml-64'">
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
                @click="sidebarCollapsed = !sidebarCollapsed; localStorage.setItem('sidebarCollapsed', sidebarCollapsed)"
                class="hidden lg:flex p-2 rounded-lg text-gray-600 hover:bg-gray-100 -ml-1 touch-manipulation transition-transform duration-200"
                :class="sidebarCollapsed ? '' : ''"
                aria-label="Toggle sidebar"
            >
                <svg class="h-6 w-6 transition-transform duration-200" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
            </button>
            <span class="font-semibold text-gray-900 truncate">@yield('sidebar_page_title', 'hsabati')</span>
        </header>
        @yield('main')
    </div>
</div>
@stack('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
