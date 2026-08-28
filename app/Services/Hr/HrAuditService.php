<?php

namespace App\Services\Hr;

use App\Models\HrAuditLog;
use Illuminate\Database\Eloquent\Model;

class HrAuditService
{
    public function log(
        Model $auditable,
        string $action,
        ?string $field = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $reason = null,
        ?int $userId = null,
    ): HrAuditLog {
        return HrAuditLog::create([
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'field' => $field,
            'old_value' => $this->stringify($oldValue),
            'new_value' => $this->stringify($newValue),
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function logChanges(Model $auditable, array $before, array $after, ?string $reason = null, ?int $userId = null): void
    {
        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;
            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $this->log($auditable, 'update', $field, $oldValue, $newValue, $reason, $userId);
        }
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
