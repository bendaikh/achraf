{{-- Largeurs fixes (pt) = 20/32/7/13/8/8/12 % d'une zone utile A4 ~527pt (marges 12mm). --}}
@php
    $colWidths = $colWidths ?? [105, 169, 37, 69, 42, 42, 63];
@endphp
<colgroup>
    <col width="{{ $colWidths[0] }}">
    <col width="{{ $colWidths[1] }}">
    <col width="{{ $colWidths[2] }}">
    <col width="{{ $colWidths[3] }}">
    <col width="{{ $colWidths[4] }}">
    <col width="{{ $colWidths[5] }}">
    <col width="{{ $colWidths[6] }}">
</colgroup>
