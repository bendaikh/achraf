@props(['items', 'showDescription' => true])

@php
    $showSourceReference = $items->contains(fn ($item) => !empty($item->source_document_reference));
@endphp

<table class="items-table">
    <thead>
        <tr>
            <th style="width: {{ $showSourceReference ? '8%' : '9%' }};">Réf</th>
            @if($showSourceReference)
                <th style="width: 12%;">Origine BR/BC</th>
            @endif
            <th style="width: {{ $showDescription ? ($showSourceReference ? '22%' : '28%') : ($showSourceReference ? '32%' : '38%') }};">Désignation</th>
            @if($showDescription)
                <th style="width: 18%;">Description</th>
            @endif
            <th class="text-right" style="width: 8%;">Qté</th>
            <th class="text-right" style="width: 12%;">Prix unit.</th>
            <th class="text-right" style="width: 8%;">TVA</th>
            <th class="text-right" style="width: 9%;">Remise</th>
            <th class="text-right" style="width: 12%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
            <tr>
                <td>{{ $item->ref ?? '-' }}</td>
                @if($showSourceReference)
                    <td>{{ $item->source_document_reference ?? '-' }}</td>
                @endif
                <td>{{ $item->designation }}</td>
                @if($showDescription)
                    <td>{{ $item->description ?? '-' }}</td>
                @endif
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ $item->tax_rate }}%</td>
                <td class="text-right">{{ number_format($item->discount ?? 0, 2) }}</td>
                <td class="text-right"><strong>{{ number_format($item->line_total, 2) }}</strong></td>
            </tr>
        @endforeach
    </tbody>
</table>
