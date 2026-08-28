<?php

namespace App\Http\Controllers;

use App\Services\TableColumnPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableColumnPreferenceController extends Controller
{
    public function __construct(
        protected TableColumnPreferenceService $preferences,
    ) {}

    public function show(Request $request, string $tableKey): JsonResponse
    {
        abort_unless($this->preferences->definition($tableKey), 404);

        return response()->json(
            $this->preferences->payloadForPage($request->user(), $tableKey)
        );
    }

    public function update(Request $request, string $tableKey): JsonResponse
    {
        abort_unless($this->preferences->definition($tableKey), 404);

        $validated = $request->validate([
            'viewport' => 'required|string|in:desktop,mobile',
            'config' => 'required|array',
            'config.order' => 'nullable|array',
            'config.order.*' => 'string|max:100',
            'config.visible' => 'nullable|array',
            'config.widths' => 'nullable|array',
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        $config = $this->preferences->saveUserConfig(
            $user,
            $tableKey,
            $validated['config'],
            $validated['viewport']
        );

        return response()->json([
            'table_key' => $tableKey,
            'viewport' => $validated['viewport'],
            'config' => $config,
        ]);
    }

    public function updateDefaults(Request $request, string $tableKey): JsonResponse
    {
        abort_unless($this->preferences->definition($tableKey), 404);

        $validated = $request->validate([
            'viewport' => 'required|string|in:desktop,mobile',
            'config' => 'required|array',
            'config.order' => 'nullable|array',
            'config.order.*' => 'string|max:100',
            'config.visible' => 'nullable|array',
            'config.widths' => 'nullable|array',
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        $config = $this->preferences->saveAdminDefault(
            $user,
            $tableKey,
            $validated['config'],
            $validated['viewport']
        );

        return response()->json([
            'table_key' => $tableKey,
            'viewport' => $validated['viewport'],
            'config' => $config,
            'saved_as_default' => true,
        ]);
    }

    public function reset(Request $request, string $tableKey): JsonResponse
    {
        abort_unless($this->preferences->definition($tableKey), 404);

        $validated = $request->validate([
            'viewport' => 'required|string|in:desktop,mobile',
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        $user->tablePreferences()
            ->where('table_key', $tableKey)
            ->where('viewport', $validated['viewport'])
            ->delete();

        return response()->json([
            'table_key' => $tableKey,
            'viewport' => $validated['viewport'],
            'config' => $this->preferences->defaultConfig($tableKey, $validated['viewport']),
        ]);
    }
}
