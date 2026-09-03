@props(['badges' => []])

@if(count($badges) === 0)
    <span class="px-2.5 py-0.5 inline-flex text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
        Non converti
    </span>
@elseif(count($badges) === 1)
    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">
        {{ $badges[0] }}
    </span>
@else
    <div class="flex flex-col items-start gap-1">
        @foreach($badges as $badge)
            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                {{ $badge }}
            </span>
        @endforeach
    </div>
@endif
