<?php

namespace App\Http\Controllers\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

trait GeneratesCommercialPdf
{
    /**
     * @param  array<string, mixed>  $viewData
     */
    protected function downloadCommercialPdf(array $viewData, string $filenamePrefix, string $documentNumber)
    {
        $pdf = Pdf::loadView('documents.pdf', $viewData);
        $pdf->setPaper('a4', 'portrait');

        $filename = $filenamePrefix.'-'.Str::slug($documentNumber).'.pdf';

        return $pdf->download($filename);
    }
}
