@props(['imported' => false, 'name' => null])

@if($imported)
    <div class="flex flex-col gap-1">
        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 w-fit">
            Importé
        </span>
        @if($name)
            <span class="text-xs text-gray-600 truncate max-w-[160px]" title="{{ $name }}">{{ $name }}</span>
        @endif
    </div>
@else
    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
        Non importé
    </span>
@endif
