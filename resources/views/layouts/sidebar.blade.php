@php
    $navigationModules = \App\Support\Navigation::modules(Auth::user());
@endphp

<div class="flex flex-col h-full">
    <div class="p-4 border-b border-gray-200 flex-shrink-0">
        <div class="flex items-center" :class="sidebarCollapsed && !sidebarOpen ? 'justify-center' : 'space-x-3'">
            <div class="h-10 w-10 bg-[#fdb819] rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm">
                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <div x-show="!sidebarCollapsed || sidebarOpen" x-transition class="overflow-hidden">
                <h1 class="text-lg font-bold text-gray-900 whitespace-nowrap">LAV'FAST</h1>
                <p class="text-xs text-gray-500 whitespace-nowrap">E-commerce Management</p>
            </div>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 space-y-1" :class="sidebarCollapsed && !sidebarOpen ? 'px-2' : 'px-3'">
        @foreach ($navigationModules as $module)
            @php
                $moduleActive = \App\Support\Navigation::isActive($module, request());
            @endphp
            <a
                href="{{ route($module['route']) }}"
                class="group relative flex items-center rounded-xl transition-all duration-150 {{ $moduleActive ? 'bg-[#fdb819] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-950' }}"
                :class="sidebarCollapsed && !sidebarOpen ? 'justify-center px-3 py-3' : 'space-x-3 px-4 py-3'"
                title="{{ $module['label'] }}"
                data-nav-module="{{ $module['key'] ?? $module['route'] }}"
                @if(($module['soft_nav'] ?? true) !== false) data-soft-nav @endif
                @if($moduleActive) aria-current="page" @endif
            >
                <span
                    data-nav-active-marker
                    class="absolute -left-3 top-1/2 h-7 w-1 -translate-y-1/2 rounded-r-full bg-[#0a5d8a]"
                    x-show="!sidebarCollapsed || sidebarOpen"
                    @if(!$moduleActive) style="display: none;" @endif
                ></span>
                <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    @foreach ($module['icon'] as $path)
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"></path>
                    @endforeach
                </svg>
                <span class="font-medium whitespace-nowrap" x-show="!sidebarCollapsed || sidebarOpen" x-transition>
                    {{ $module['label'] }}
                </span>
                @if(!empty($module['children']))
                    <svg
                        class="ml-auto h-4 w-4 flex-shrink-0 {{ $moduleActive ? 'text-white/80' : 'text-gray-400 group-hover:text-gray-600' }}"
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

    <div class="flex-shrink-0 border-t border-gray-200 bg-white" :class="sidebarCollapsed && !sidebarOpen ? 'p-2' : 'p-4'">
        <div class="flex items-center mb-3" :class="sidebarCollapsed && !sidebarOpen ? 'justify-center' : 'space-x-3'">
            <div class="h-10 w-10 bg-[#fdb819] rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-white font-semibold text-sm">{{ substr(Auth::user()->name, 0, 2) }}</span>
            </div>
            <div class="flex-1 min-w-0" x-show="!sidebarCollapsed || sidebarOpen" x-transition>
                <p class="text-sm font-medium text-gray-900 truncate">{{ Auth::user()->name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</p>
            </div>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="w-full flex items-center border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition duration-150" :class="sidebarCollapsed && !sidebarOpen ? 'justify-center px-2 py-2' : 'justify-center space-x-2 px-4 py-2'" title="Déconnexion">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span x-show="!sidebarCollapsed || sidebarOpen" x-transition>Déconnexion</span>
            </button>
        </form>
    </div>
</div>
