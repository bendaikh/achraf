<?php

namespace App\Models\Concerns;

use App\Models\ManagedDocument;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasManagedDocuments
{
    public function managedDocuments(): MorphMany
    {
        return $this->morphMany(ManagedDocument::class, 'documentable');
    }

    public function activeManagedDocuments(): MorphMany
    {
        return $this->managedDocuments()->where('is_active', true)->orderBy('sequence');
    }

    public function primaryManagedDocument(): ?ManagedDocument
    {
        return $this->activeManagedDocuments()
            ->where('category', 'primary')
            ->orderByDesc('id')
            ->first();
    }

    public function hasManagedDocument(?string $category = 'primary'): bool
    {
        $query = $this->activeManagedDocuments();

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->exists();
    }
}
