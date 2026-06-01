@php
    $company = $company ?? \App\Support\CompanyInfo::all();
    $cachetSrc = $cachetSrc ?? ($company['cachet_url'] ?? null);
@endphp
@if($cachetSrc)
    <div class="print-company-stamp">
        <img src="{{ $cachetSrc }}" alt="Cachet {{ $company['name'] ?? '' }}" class="print-company-stamp-img">
    </div>
@endif
