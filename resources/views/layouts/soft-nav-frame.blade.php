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
        @include('layouts.module-tabs-nav')
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
