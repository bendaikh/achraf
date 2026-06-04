<?php

namespace App\Http\Controllers;

use App\Services\StockReportExportService;
use Illuminate\Http\Request;

class StockReportController extends Controller
{
    public function __construct(
        protected StockReportExportService $exportService
    ) {}

    public function exportEnligne(Request $request, string $format)
    {
        return $this->respond($format, 'enligne', $request);
    }

    public function exportMagasin(Request $request, string $format)
    {
        return $this->respond($format, 'magasin', $request);
    }

    protected function respond(string $format, string $type, Request $request)
    {
        return match ($format) {
            'excel', 'xlsx' => $this->exportService->exportExcel($type, $request),
            'pdf' => $this->exportService->exportPdf($type, $request),
            default => abort(404),
        };
    }
}
