<?php

namespace App\Models\Concerns;

use App\Models\DocumentAdjustment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDocumentAdjustments
{
    public static function bootHasDocumentAdjustments(): void
    {
        static::deleting(function ($model) {
            $model->adjustments()->delete();
        });
    }

    public function adjustments(): MorphMany
    {
        return $this->morphMany(DocumentAdjustment::class, 'adjustable')->orderBy('sort_order')->orderBy('id');
    }
}
