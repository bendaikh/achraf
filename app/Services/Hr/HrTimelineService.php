<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\HrEvent;
use Illuminate\Database\Eloquent\Model;

class HrTimelineService
{
    public function record(
        Employee $employee,
        string $type,
        string $title,
        \DateTimeInterface|string|null $date = null,
        ?string $description = null,
        ?Model $source = null,
        ?int $userId = null,
    ): HrEvent {
        return HrEvent::create([
            'employee_id' => $employee->id,
            'event_date' => $date ?? now(),
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'user_id' => $userId ?? auth()->id(),
        ]);
    }
}
