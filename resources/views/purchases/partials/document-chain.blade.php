@isset($documentChain)
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">Chaîne documentaire</h3>
    <ul class="space-y-1 text-sm text-gray-800">
        @foreach($documentChain as $i => $node)
            <li class="flex items-center gap-2 {{ $i === 0 ? 'font-semibold' : 'pl-4 text-gray-700' }}">
                @if($i > 0)<span class="text-gray-400">↳</span>@endif
                @if(!empty($node['url']))
                    <a href="{{ $node['url'] }}" class="text-[#0a5d8a] hover:underline">{{ $node['label'] }} {{ $node['number'] }}</a>
                @else
                    <span>{{ $node['label'] }} {{ $node['number'] }}</span>
                @endif
                @if(!empty($node['reception_status']))
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-700">{{ $node['reception_status'] }}</span>
                @endif
                @if(!empty($node['stock_received']))
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">Stock réceptionné ✓</span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
@endisset
