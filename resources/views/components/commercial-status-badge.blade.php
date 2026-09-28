@props([
    'status' => null,
])

@php
    $status = $status ?: \App\Support\InvoiceCommercialStatus::NORMAL;
    $badge = \App\Support\InvoiceCommercialStatus::badgeClasses()[$status] ?? 'bg-gray-100 text-gray-800';
    $label = \App\Support\InvoiceCommercialStatus::labels()[$status] ?? $status;
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$badge}"]) }}>
    {{ $label }}
</span>
