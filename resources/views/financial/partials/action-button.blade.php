@php
    $isPost = ($action['method'] ?? 'GET') === 'POST';
    $modal = $action['modal'] ?? null;
    $classes = !empty($linkStyle)
        ? 'text-sm text-gray-500 hover:text-gray-800 text-center mt-1'
        : ($primary
            ? 'inline-flex items-center justify-center w-full px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#0a5d8a] hover:bg-[#084a6e]'
            : 'inline-flex items-center justify-center w-full px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50');
@endphp

@if($modal)
    <button type="button"
            onclick="document.getElementById('{{ $modal }}')?.classList.remove('hidden')"
            class="{{ $classes }}">
        {{ $action['label'] }}
    </button>
@elseif($isPost)
    <form method="POST" action="{{ $action['url'] }}" class="{{ !empty($linkStyle) ? '' : 'w-full' }}">
        @csrf
        @foreach(request()->only(['date_from', 'date_to', 'month']) as $key => $value)
            @if($value !== null && $value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <button type="submit" class="{{ $classes }}">{{ $action['label'] }}</button>
    </form>
@else
    <a href="{{ $action['url'] }}" class="{{ $classes }}">{{ $action['label'] }}</a>
@endif
