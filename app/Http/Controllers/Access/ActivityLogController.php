<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = ActivityLog::query()->with('user')->latest('id');

        $this->applyTableSearch($query, $request, ['action', 'summary', 'document_ref']);
        $this->applyTableFilter($query, $request, 'action', 'action');
        $this->applyTableDateRange($query, $request, 'created_at');

        return view('access.activity.index', [
            'logs' => $this->paginateTable($query, $request, 25),
        ]);
    }
}
