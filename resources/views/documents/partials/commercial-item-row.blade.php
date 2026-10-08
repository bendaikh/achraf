@if(($row['type'] ?? null) === 'origin')
    <tr class="facture-origin-group">
        <td colspan="7">
            <table class="facture-origin-header" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="facture-origin-left">
                        <strong>Origine :</strong> {{ $row['label'] }}
                    </td>
                    @if(!empty($row['date']))
                        <td class="facture-origin-right">
                            <strong>Du :</strong> {{ $row['date'] }}
                        </td>
                    @endif
                </tr>
            </table>
        </td>
    </tr>
@else
    @php
        $item = $row['item'];
        $line = \App\Support\LineItemCalculator::forDisplay($item, $priceMode);
    @endphp
    <tr>
        <td class="{{ $itemColumns[0]['class'] }}" style="width: {{ $itemColumns[0]['width'] }}">{{ $item->ref ?? '-' }}</td>
        <td class="{{ $itemColumns[1]['class'] }}" style="width: {{ $itemColumns[1]['width'] }}">{{ $item->designation }}</td>
        <td class="text-right {{ $itemColumns[2]['class'] }}" style="width: {{ $itemColumns[2]['width'] }}">{{ $item->quantity }}</td>
        <td class="text-right {{ $itemColumns[3]['class'] }}" style="width: {{ $itemColumns[3]['width'] }}">{{ number_format($line['unit_price_ht'], 2) }}</td>
        <td class="text-center {{ $itemColumns[4]['class'] }}" style="width: {{ $itemColumns[4]['width'] }}">{{ number_format($item->tax_rate, 2) }}%</td>
        <td class="text-right {{ $itemColumns[5]['class'] }}" style="width: {{ $itemColumns[5]['width'] }}">{{ number_format($item->discount ?? 0, 2) }}</td>
        <td class="text-right {{ $itemColumns[6]['class'] }}" style="width: {{ $itemColumns[6]['width'] }}">
            <strong>{{ number_format($line['line_total'], 2) }}</strong>
        </td>
    </tr>
@endif
