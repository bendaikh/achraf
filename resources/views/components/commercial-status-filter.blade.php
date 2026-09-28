@props([
    'name' => 'commercial_status',
    'label' => 'Statut commercial',
    'placeholder' => 'Tous',
    'options' => null,
])

@php
    $options = $options ?? \App\Support\InvoiceCommercialStatus::filterOptions();
@endphp

<x-table-filter-select
    :name="$name"
    :label="$label"
    :options="$options"
    :placeholder="$placeholder"
/>
