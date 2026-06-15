<?php

namespace App\Http\Controllers;

use App\Services\BulkCommercialPdfExportService;
use App\Services\TableExportService;
use Illuminate\Http\Request;

class TableExportController extends Controller
{
    public function __construct(
        protected TableExportService $exportService,
        protected BulkCommercialPdfExportService $pdfZipService,
    ) {}

    public function export(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        if (! in_array($validated['type'], $this->exportService->types(), true)) {
            return response()->json(['message' => 'Type d\'export invalide.'], 422);
        }

        return $this->exportService->export($validated['type'], $validated['ids']);
    }

    public function exportZip(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        if (! $this->pdfZipService->supportsZip($validated['type'])) {
            return response()->json(['message' => 'Export ZIP PDF non disponible pour ce type.'], 422);
        }

        return $this->pdfZipService->exportZip($validated['type'], $validated['ids']);
    }
}
