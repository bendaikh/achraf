@props(['exportType'])
<th class="px-4 py-3 text-left">
    <input type="checkbox" id="selectAll-{{ $exportType }}" onchange="toggleTableSelectAll(this, '{{ $exportType }}')" class="rounded border-gray-300 text-[#0a5d8a] focus:ring-[#0a5d8a]">
</th>
