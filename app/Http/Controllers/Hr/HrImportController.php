<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\HrImportService;
use Illuminate\Http\Request;

class HrImportController extends Controller
{
    public function template(HrImportService $import)
    {
        return $import->downloadTemplate();
    }

    public function store(Request $request, HrImportService $import)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $result = $import->importEmployees($request->file('file'));
        $redirect = redirect()->route('hr.employees.index');

        $messages = [];
        if ($result['created'] > 0) {
            $messages[] = $result['created'].' salarié(s) importé(s).';
        }
        if ($result['skipped'] > 0) {
            $messages[] = $result['skipped'].' ligne(s) ignorée(s) (CIN déjà existant).';
        }
        if ($messages !== []) {
            $redirect->with('success', implode(' ', $messages));
        }
        if ($result['errors'] !== []) {
            $redirect->with('import_errors', $result['errors']);
        }

        return $redirect;
    }
}
