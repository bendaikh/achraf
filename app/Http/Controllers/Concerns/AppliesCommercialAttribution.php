<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Access\CommercialAttributionService;
use Illuminate\Http\Request;

trait AppliesCommercialAttribution
{
    /**
     * @return array{collaborator_id: ?int, created_by_user_id: ?int}
     */
    protected function commercialCreateAttributes(Request $request): array
    {
        $id = $request->filled('collaborator_id') ? (int) $request->input('collaborator_id') : null;

        return app(CommercialAttributionService::class)->createAttributes($id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function commercialValidationRules(): array
    {
        return [
            'collaborator_id' => ['nullable', 'exists:collaborators,id'],
        ];
    }
}
