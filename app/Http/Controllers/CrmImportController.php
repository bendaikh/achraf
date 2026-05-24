<?php

namespace App\Http\Controllers;

use App\Services\CrmImportService;
use Illuminate\Http\Request;

class CrmImportController extends Controller
{
  public function __construct(
    private CrmImportService $importService
  ) {}

  public function clientTemplate()
  {
    return $this->importService->downloadClientTemplate();
  }

  public function supplierTemplate()
  {
    return $this->importService->downloadSupplierTemplate();
  }

  public function importClients(Request $request)
  {
    $request->validate([
      'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
    ]);

    $result = $this->importService->importClients($request->file('file'));

    return $this->redirectWithResult('clients.index', $result, 'client(s)');
  }

  public function importSuppliers(Request $request)
  {
    $request->validate([
      'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
    ]);

    $result = $this->importService->importSuppliers($request->file('file'));

    return $this->redirectWithResult('suppliers.index', $result, 'fournisseur(s)');
  }

  /**
   * @param  array{created: int, skipped: int, errors: list<string>}  $result
   */
  private function redirectWithResult(string $route, array $result, string $label)
  {
    $redirect = redirect()->route($route);
    $messages = [];

    if ($result['created'] > 0) {
      $messages[] = "{$result['created']} {$label} créé(s).";
    }
    if ($result['skipped'] > 0) {
      $messages[] = "{$result['skipped']} ligne(s) ignorée(s) (téléphone déjà existant).";
    }

    if (! empty($messages)) {
      $redirect->with('success', implode(' ', $messages));
    }

    if (! empty($result['errors'])) {
      $redirect->with('import_errors', $result['errors']);
      if ($result['created'] === 0 && $result['skipped'] === 0) {
        $redirect->with('error', 'Aucun enregistrement importé.');
      }
    }

    if ($result['created'] === 0 && $result['skipped'] === 0 && empty($result['errors'])) {
      $redirect->with('error', 'Le fichier est vide ou invalide.');
    }

    return $redirect;
  }
}
