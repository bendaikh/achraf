<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'LAV\'FAST')</title>
</head>
<body>
@php
    $navigationModules = \App\Support\Navigation::modules(Auth::user());
    $activeNavigationModule = \App\Support\Navigation::activeModule($navigationModules, request());
    $moduleTabs = $activeNavigationModule['children'] ?? [];
@endphp
<span id="app-page-title">@yield('sidebar_page_title', 'hsabati')</span>
<div id="app-module-tabs" @if ($moduleTabs === []) hidden @endif>
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
</div>
<div id="app-page-root">
    @yield('main')
    @stack('scripts')
</div>
</body>
</html>
