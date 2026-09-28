@props([
    'name' => 'source',
    'label' => 'Source',
    'placeholder' => 'Toutes',
    'options' => null,
])

@php
    $options = $options ?? [
        'shopify' => 'Shopify',
        'jumia' => 'Jumia',
        'libromart' => 'Vente directe',
    ];
@endphp

<x-table-filter-select
    :name="$name"
    :label="$label"
    :options="$options"
    :placeholder="$placeholder"
/>
