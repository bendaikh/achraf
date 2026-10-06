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
        <td width="105">{{ $item->ref ?? '-' }}</td>
        <td width="169">{{ $item->designation }}</td>
        <td class="text-right" width="37">{{ $item->quantity }}</td>
        <td class="text-right" width="69">{{ number_format($line['unit_price_ht'], 2) }}</td>
        <td class="text-center" width="42">{{ number_format($item->tax_rate, 2) }}%</td>
        <td class="text-right" width="42">{{ number_format($item->discount ?? 0, 2) }}</td>
        <td class="text-right" width="63"><strong>{{ number_format($line['line_total'], 2) }}</strong></td>
    </tr>
@endif
