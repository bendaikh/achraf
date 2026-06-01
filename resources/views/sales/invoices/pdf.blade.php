<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice->invoice_number }}</title>
    @include('sales.invoices.partials.facture-styles')
</head>
<body>
    @include('sales.invoices.partials.facture-document', [
        'logoSrc' => \App\Support\CompanyInfo::logoFilePathForPdf(),
        'forPdf' => true,
    ])
</body>
</html>
