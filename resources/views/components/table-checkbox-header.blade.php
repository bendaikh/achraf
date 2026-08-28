@props(['exportType'])
<th class="px-4 py-3 text-left w-12 lm-col lm-col-select column-select" data-lm-col="select">
    <label class="inline-flex items-center cursor-pointer">
        <input
            type="checkbox"
            id="selectAll-{{ $exportType }}"
            class="table-select-all h-4 w-4 rounded border-gray-300 text-[#fdb819] focus:ring-[#fdb819] cursor-pointer"
            data-export-type="{{ $exportType }}"
            aria-label="Tout sélectionner"
        >
    </label>
</th>
