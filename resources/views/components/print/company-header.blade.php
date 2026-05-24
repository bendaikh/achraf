@php
    $company = $company ?? \App\Support\CompanyInfo::all();
    $address = \App\Support\CompanyInfo::formattedAddress();
    $legalLines = \App\Support\CompanyInfo::legalLines();
@endphp
<div class="print-header">
    <div class="company-block">
        @if(!empty($company['logo_url']))
            <img src="{{ $company['logo_url'] }}" alt="{{ $company['name'] }}" class="company-logo">
        @endif
        <div>
            <div class="company-name">{{ $company['name'] }}</div>
            @if($address)
                <div class="company-details">{{ $address }}</div>
            @endif
            @if($company['phone'] || $company['email'])
                <div class="company-details" style="margin-top: 6px;">
                    @if($company['phone'])Tél: {{ $company['phone'] }}@endif
                    @if($company['phone'] && $company['email'])<br>@endif
                    @if($company['email'])Email: {{ $company['email'] }}@endif
                </div>
            @endif
            @if(count($legalLines))
                <div class="company-legal">{{ implode("\n", $legalLines) }}</div>
            @endif
        </div>
    </div>
    <div class="document-meta">
        <div class="document-title">{{ $documentTitle }}</div>
        @if(!empty($documentNumber))
            <div class="document-number">{{ $documentNumber }}</div>
        @endif
        {{ $slot }}
    </div>
</div>
