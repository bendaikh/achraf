<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialReassignment extends Model
{
    protected $fillable = [
        'document_type',
        'document_id',
        'document_ref',
        'from_collaborator_id',
        'to_collaborator_id',
        'changed_by',
        'reason',
    ];

    public function fromCollaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class, 'from_collaborator_id');
    }

    public function toCollaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class, 'to_collaborator_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
