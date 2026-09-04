<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    public static function log(
        string $action,
        ?string $summary = null,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $documentRef = null,
        ?User $user = null,
    ): ActivityLog {
        $actor = $user ?? Auth::user();

        return ActivityLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'document_ref' => $documentRef,
            'summary' => $summary,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
