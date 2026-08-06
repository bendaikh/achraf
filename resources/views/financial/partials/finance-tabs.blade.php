{{-- Horizontal module switcher for Gestion Financière --}}
@php
    $financeTabs = [
        ['label' => 'Vue d\'ensemble', 'route' => 'financial.index', 'match' => ['financial.index']],
        ['label' => 'TVA', 'route' => 'financial.tva', 'match' => ['financial.tva', 'financial.tva.*']],
        ['label' => 'Trésorerie', 'route' => 'financial.tresorerie', 'match' => ['financial.tresorerie', 'financial.tresorerie.*']],
        ['label' => 'Achats & dépenses', 'route' => 'financial.achats-depenses', 'match' => ['financial.achats-depenses']],
        ['label' => 'Créances & dettes', 'route' => 'financial.creances-dettes', 'match' => ['financial.creances-dettes', 'financial.creances-dettes.*']],
        ['label' => 'Mouvements', 'route' => 'financial.mouvements.index', 'match' => ['financial.mouvements.*']],
        ['label' => 'Déclarations', 'route' => 'financial.declarations', 'match' => ['financial.declarations', 'financial.declarations.*', 'financial.export']],
    ];
    $query = request()->only(['date_from', 'date_to', 'month']);
@endphp

<nav class="border-b border-slate-200 bg-white">
    <div class="px-4 sm:px-6 lg:px-8 overflow-x-auto">
        <div class="flex gap-1 min-w-max py-0">
            @foreach($financeTabs as $tab)
                @php $active = request()->routeIs(...$tab['match']); @endphp
                <a href="{{ route($tab['route'], $query) }}"
                   class="relative px-4 py-3 text-sm font-medium whitespace-nowrap transition
                          {{ $active ? 'text-[#0a5d8a]' : 'text-slate-500 hover:text-slate-800' }}">
                    {{ $tab['label'] }}
                    @if($active)
                        <span class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-[#0a5d8a]"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</nav>
