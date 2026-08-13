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
    <!--soft-nav:tabs:start-->
    @if ($moduleTabs !== [])
        <nav class="module-tabs-scroll overflow-x-auto border-b border-gray-200 bg-white" aria-label="Menus de {{ $activeNavigationModule['label'] ?? '' }}">
            <div class="flex min-w-max items-center gap-1 px-4">
                @foreach ($moduleTabs as $tab)
                    @php
                        $tabActive = \App\Support\Navigation::isActive($tab, request());
                    @endphp
                    <a
                        href="{{ \App\Support\Navigation::url($tab) }}"
                        data-soft-nav
                        @if(! empty($tab['list_reset'])) data-list-reset @endif
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
<div id="app-page-root">
    <!--soft-nav:page:start-->
    @yield('main')
    @stack('scripts')
    <!--soft-nav:page:end-->
</div>
</body>
</html>
