<?php

namespace App\Models\Concerns;

use App\Models\Collaborator;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasCommercialAttribution
{
    public function commercial(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class, 'collaborator_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
