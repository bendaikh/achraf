@props([
    'key',
    'tag' => 'td',
])

<{{ $tag }}
    {{ $attributes->merge(['class' => 'lm-col lm-col-' . $key . ' column-' . $key]) }}
    data-lm-col="{{ $key }}"
>{{ $slot }}</{{ $tag }}>
