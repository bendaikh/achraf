<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableExport extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'ids',
        'export_options',
        'status',
        'progress',
        'total_rows',
        'filename',
        'path',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'ids' => 'array',
        'export_options' => 'array',
        'completed_at' => 'datetime',
    ];

    public function isOwnedBy(?int $userId): bool
    {
        return $this->user_id === null || $this->user_id === $userId;
    }
}
