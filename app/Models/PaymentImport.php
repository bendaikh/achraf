<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentImport extends Model
{
    public const SCOPE_SALES = 'sales';

    public const SCOPE_PURCHASES = 'purchases';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'scope',
        'status',
        'file_name',
        'file_path',
        'file_type',
        'file_hash',
        'uploaded_by',
        'uploaded_at',
        'total_rows',
        'matched_count',
        'ambiguous_count',
        'not_found_count',
        'duplicate_count',
        'payment_date',
        'payment_method',
        'payment_reference',
        'notes',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'validated_at' => 'datetime',
        'uploaded_at' => 'datetime',
    ];

    protected $appends = [
        'original_filename',
        'lines_count',
        'unmatched_count',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PaymentImportLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function getOriginalFilenameAttribute(): ?string
    {
        return $this->attributes['file_name'] ?? null;
    }

    public function setOriginalFilenameAttribute(?string $value): void
    {
        $this->attributes['file_name'] = $value;
    }

    public function getStoredPathAttribute(): ?string
    {
        return $this->attributes['file_path'] ?? null;
    }

    public function setStoredPathAttribute(?string $value): void
    {
        $this->attributes['file_path'] = $value;
    }

    public function getLinesCountAttribute(): int
    {
        return (int) ($this->attributes['total_rows'] ?? 0);
    }

    public function setLinesCountAttribute($value): void
    {
        $this->attributes['total_rows'] = (int) $value;
    }

    public function getUnmatchedCountAttribute(): int
    {
        return (int) ($this->attributes['not_found_count'] ?? 0);
    }

    public function setUnmatchedCountAttribute($value): void
    {
        $this->attributes['not_found_count'] = (int) $value;
    }

    public function getUserIdAttribute(): ?int
    {
        return isset($this->attributes['uploaded_by']) ? (int) $this->attributes['uploaded_by'] : null;
    }
}
