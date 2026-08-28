<?php

namespace App\Models;

use App\Support\DocumentAdjustmentCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentAdjustment extends Model
{
    protected $fillable = [
        'label',
        'type',
        'amount',
        'is_taxable',
        'tax_rate',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_taxable' => 'boolean',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function adjustable(): MorphTo
    {
        return $this->morphTo();
    }

    public function signedTotal(): float
    {
        return DocumentAdjustmentCalculator::compute($this)['signed_total'];
    }
}
