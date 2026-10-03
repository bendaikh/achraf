{{--
    Module tab bar (sub-menus of the active sidebar module).
    Tabs may declare a 'group' label: consecutive tabs sharing a group are rendered
    together behind a small group caption, with a divider between groups.
--}}
@php
    $tabGroups = [];
    foreach ($moduleTabs as $tab) {
        $groupLabel = $tab['group'] ?? null;
        $lastIndex = count($tabGroups) - 1;
        if ($lastIndex >= 0 && $tabGroups[$lastIndex]['label'] === $groupLabel) {
            $tabGroups[$lastIndex]['tabs'][] = $tab;
        } else {
            $tabGroups[] = ['label' => $groupLabel, 'tabs' => [$tab]];
        }
    }
    $hasTabGroups = collect($tabGroups)->contains(fn ($g) => $g['label'] !== null);
@endphp
<nav class="module-tabs-scroll overflow-x-auto border-b border-gray-200 bg-white" aria-label="Menus de {{ $activeNavigationModule['label'] ?? '' }}">
    <div class="flex min-w-max items-center gap-1 px-4">
        @foreach ($tabGroups as $groupIndex => $group)
            @if ($hasTabGroups && $groupIndex > 0)
                <span class="mx-2 h-7 w-px shrink-0 bg-gray-200" aria-hidden="true"></span>
            @endif
            <div class="flex items-center gap-1" @if($group['label']) role="group" aria-label="{{ $group['label'] }}" data-tab-group="{{ $group['label'] }}" @endif>
                @if ($group['label'])
                    <span class="mr-1 inline-flex items-center rounded-md bg-[#0a5d8a]/5 px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-[#0a5d8a]/70 whitespace-nowrap">{{ $group['label'] }}</span>
                @endif
                @foreach ($group['tabs'] as $tab)
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
        @endforeach
    </div>
</nav>
