<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ManagedDocument;
use App\Support\DocumentAttachmentRegistry;
use Illuminate\Http\Request;

class HrDocumentController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = ManagedDocument::query()
            ->where('section_key', 'hr-employees')
            ->with(['documentable', 'currentVersion']);

        if ($request->filled('employee_id')) {
            $query->where('documentable_type', (new Employee)->getMorphClass())
                ->where('documentable_id', $request->integer('employee_id'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->boolean('expiring')) {
            $days = (int) \App\Models\Setting::get('hr.alert.document_expiry_days', 30);
            $query->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now()->toDateString(), now()->addDays($days)->toDateString()]);
        }

        $this->applyTableSort($query, $request, [
            'document_date' => 'document_date',
            'expires_at' => 'expires_at',
        ], 'document_date', 'desc');

        $categories = DocumentAttachmentRegistry::get('hr-employees')['categories'] ?? [];

        return view('hr.documents.index', [
            'documents' => $this->paginateTable($query, $request),
            'employees' => Employee::query()->orderBy('last_name')->get(),
            'categories' => $categories,
        ]);
    }
}
