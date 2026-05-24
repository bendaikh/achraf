@props(['exportType', 'id'])
<td class="px-4 py-4 whitespace-nowrap w-12">
    <label class="inline-flex items-center cursor-pointer" onclick="event.stopPropagation()">
        <input
            type="checkbox"
            class="table-row-checkbox h-4 w-4 rounded border-gray-300 text-[#fdb819] focus:ring-[#fdb819] cursor-pointer"
            data-export-type="{{ $exportType }}"
            value="{{ $id }}"
            onchange="updateTableSelectedCount('{{ $exportType }}')"
            aria-label="Sélectionner la ligne"
        >
    </label>
</td>
