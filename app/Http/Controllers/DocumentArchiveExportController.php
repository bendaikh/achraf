<?php

namespace App\Http\Controllers;

use App\Services\Documents\DocumentArchiveExportService;
use App\Support\DocumentAttachmentRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentArchiveExportController extends Controller
{
    public function __construct(
        protected DocumentArchiveExportService $exports
    ) {}

    public function index(Request $request)
    {
        $sections = collect(DocumentAttachmentRegistry::all())
            ->filter(fn (array $config) => ($config['exportable'] ?? false) === true)
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'],
            ])
            ->values();

        $defaults = [
            'date_from' => $request->input('date_from', now()->startOfMonth()->toDateString()),
            'date_to' => $request->input('date_to', now()->endOfMonth()->toDateString()),
            'sections' => $request->input('sections', $sections->pluck('key')->all()),
        ];

        $inspection = null;
        if ($request->boolean('preview')) {
            $validated = $this->validateSelection($request);
            $inspection = $this->exports->inspect(
                $validated['date_from'],
                $validated['date_to'],
                $validated['sections']
            );
        }

        return view('documents.archive.export', compact('sections', 'defaults', 'inspection'));
    }

    public function inspect(Request $request)
    {
        $validated = $this->validateSelection($request);
        $inspection = $this->exports->inspect(
            $validated['date_from'],
            $validated['date_to'],
            $validated['sections']
        );

        return redirect()
            ->route('documents.archive.index', [
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'sections' => $validated['sections'],
                'preview' => 1,
            ])
            ->with('inspection', $inspection);
    }

    public function export(Request $request)
    {
        $validated = $this->validateSelection($request);
        $validated['format'] = $request->validate([
            'format' => 'required|in:excel,zip,pdf',
            'allow_missing' => 'sometimes|boolean',
        ])['format'];

        $allowMissing = $request->boolean('allow_missing');

        $result = match ($validated['format']) {
            'excel' => $this->exports->exportExcel(
                $validated['date_from'],
                $validated['date_to'],
                $validated['sections']
            ),
            'zip' => $this->exports->exportZip(
                $validated['date_from'],
                $validated['date_to'],
                $validated['sections'],
                $allowMissing
            ),
            'pdf' => $this->exports->exportMergedPdf(
                $validated['date_from'],
                $validated['date_to'],
                $validated['sections'],
                $allowMissing
            ),
        };

        if (($result['blocked'] ?? false) === true) {
            return redirect()
                ->route('documents.archive.index', [
                    'date_from' => $validated['date_from'],
                    'date_to' => $validated['date_to'],
                    'sections' => $validated['sections'],
                    'preview' => 1,
                ])
                ->with('inspection', $result['inspection'])
                ->with('error', 'Des pièces sont manquantes. Ajoutez-les, annulez, ou continuez malgré tout.');
        }

        return Storage::disk($result['disk'])->download($result['path'], $result['filename']);
    }

    /**
     * @return array{date_from: string, date_to: string, sections: list<string>}
     */
    protected function validateSelection(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'sections' => 'required|array|min:1',
            'sections.*' => 'string|in:'.implode(',', DocumentAttachmentRegistry::exportableKeys()),
        ]);

        return [
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'sections' => array_values($validated['sections']),
        ];
    }
}
