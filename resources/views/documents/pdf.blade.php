<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $doc['title'] ?? 'Document' }} {{ $doc['number'] ?? '' }}</title>
    @include('documents.partials.commercial-styles')
</head>
<body>
    @include('documents.partials.commercial-document', [
        'logoSrc' => \App\Support\CompanyInfo::logoFilePathForPdf(),
        'forPdf' => true,
    ])
</body>
</html>
