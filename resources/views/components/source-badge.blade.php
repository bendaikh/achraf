@props([
    'source' => null,
    'auto' => false,
])

@php
    $source = $source ?: null;
@endphp

@if($source === 'shopify')
    <span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800']) }}>Shopify</span>
@elseif($source === 'jumia')
    <span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800']) }}>Jumia</span>
@elseif($auto)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800']) }}>Auto</span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800']) }}>Directe</span>
@endif
