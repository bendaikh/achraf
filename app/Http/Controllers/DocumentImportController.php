<?php

namespace App\Http\Controllers;

use App\Services\BulkImport\BulkDocumentImportService;
use App\Services\BulkImport\DocumentImportRegistry;
use Illuminate\Http\Request;

class DocumentImportController extends Controller
{
    public function __construct(
        private BulkDocumentImportService $importService
    ) {}

    public function downloadTemplate(string $type)
    {
        $this->ensureValidType($type);

        return $this->importService->downloadTemplate($type);
    }

    public function import(Request $request, string $type)
    {
        $this->ensureValidType($type);
        $config = DocumentImportRegistry::get($type);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $result = $this->importService->import($type, $request->file('file'));
        } catch (\Throwable $e) {
            return redirect()
                ->route($config['redirect_route'])
                ->with('error', 'Erreur lors de l\'import: ' . $e->getMessage());
        }

        $redirect = redirect()->route($config['redirect_route']);

        if ($result['created'] > 0) {
            $redirect->with('success', "{$result['created']} document(s) importé(s) avec succès.");
        }

        if (! empty($result['errors'])) {
            $redirect->with('import_errors', $result['errors']);

            if ($result['created'] === 0) {
                $redirect->with('error', 'Aucun document n\'a pu être importé.');
            }
        }

        return $redirect;
    }

    private function ensureValidType(string $type): void
    {
        if (! in_array($type, DocumentImportRegistry::TYPES, true)) {
            abort(404);
        }
    }
}
