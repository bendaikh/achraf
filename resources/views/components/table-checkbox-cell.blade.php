@props(['exportType', 'id'])
<td class="px-4 py-4 whitespace-nowrap">
    <input type="checkbox" class="table-row-checkbox rounded border-gray-300 text-[#0a5d8a] focus:ring-[#0a5d8a]" data-export-type="{{ $exportType }}" value="{{ $id }}" onchange="updateTableSelectedCount('{{ $exportType }}')">
</td>
