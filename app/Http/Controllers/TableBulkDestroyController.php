<?php

namespace App\Http\Controllers;

use App\Services\TableBulkDestroyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableBulkDestroyController extends Controller
{
    public function __invoke(Request $request, TableBulkDestroyService $service): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        if (! $service->supports($validated['type'])) {
            return response()->json([
                'message' => 'La suppression groupée n’est pas disponible pour cette liste.',
                'deleted' => 0,
                'blocked' => [],
            ], 422);
        }

        return response()->json(
            $service->destroyMany($validated['type'], $validated['ids'])
        );
    }
}
