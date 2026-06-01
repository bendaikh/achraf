@php
    $company = $company ?? \App\Support\CompanyInfo::all();
    $cachet = $cachet ?? \App\Support\CompanyInfo::cachetForPrint($forPdf ?? false, 200, 72);
@endphp
@if($cachet)
    <div class="print-company-stamp">
        <img
            src="{{ $cachet['src'] }}"
            alt="Cachet {{ $company['name'] ?? '' }}"
            class="print-company-stamp-img"
            width="{{ $cachet['width'] }}"
            height="{{ $cachet['height'] }}"
        >
    </div>
@endif
