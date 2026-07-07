<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTableExport;
use App\Models\TableExport;
use App\Services\BulkCommercialPdfExportService;
use App\Services\TableExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        $export = TableExport::create([
            'user_id' => $request->user()?->id,
            'type' => $validated['type'],
            'status' => 'pending',
            'progress' => 0,
            'total_rows' => count($validated['ids']),
        ]);

        GenerateTableExport::dispatch($export->id, $validated['type'], array_map('intval', $validated['ids']))
            ->onConnection('database')
            ->afterResponse();

        return response()->json([
            'message' => 'Votre export est en cours de génération en arrière-plan.',
            'export_id' => $export->id,
            'status_url' => route('table.export.status', $export),
        ], 202);
    }

    public function status(TableExport $export, Request $request)
    {
        abort_unless($export->isOwnedBy($request->user()?->id), 403);

        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'progress' => $export->progress,
            'filename' => $export->filename,
            'download_url' => $export->status === 'completed' ? route('table.export.download', $export) : null,
            'error_message' => $export->error_message,
        ]);
    }

    public function download(TableExport $export, Request $request)
    {
        abort_unless($export->isOwnedBy($request->user()?->id), 403);
        abort_unless($export->status === 'completed' && $export->path, 404);
        abort_unless(Storage::disk('public')->exists($export->path), 404);

        return Storage::disk('public')->download($export->path, $export->filename);
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
