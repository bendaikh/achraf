<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentImport extends Model
{
    public const SCOPE_SALES = 'sales';

    public const SCOPE_PURCHASES = 'purchases';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

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
        'progress',
        'processed_rows',
        'error_message',
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isAnalyzing(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'En file d’attente',
            self::STATUS_PROCESSING => 'Analyse en cours',
            self::STATUS_DRAFT => 'Prêt à contrôler',
            self::STATUS_VALIDATED => 'Validé',
            self::STATUS_FAILED => 'Échec',
            self::STATUS_CANCELLED => 'Annulé',
            default => (string) $this->status,
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING, self::STATUS_PROCESSING => 'bg-blue-50 text-blue-800 border-blue-200',
            self::STATUS_DRAFT => 'bg-amber-50 text-amber-800 border-amber-200',
            self::STATUS_VALIDATED => 'bg-green-50 text-green-800 border-green-200',
            self::STATUS_FAILED => 'bg-red-50 text-red-800 border-red-200',
            default => 'bg-gray-50 text-gray-700 border-gray-200',
        };
    }

    /**
     * Imports still needing attention (processing / draft / failed).
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function recentForScope(string $scope, int $limit = 10)
    {
        return static::query()
            ->with('user')
            ->where('scope', $scope)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_PROCESSING,
                self::STATUS_DRAFT,
                self::STATUS_FAILED,
            ])
            ->orderByRaw("CASE status
                WHEN 'processing' THEN 0
                WHEN 'pending' THEN 1
                WHEN 'draft' THEN 2
                WHEN 'failed' THEN 3
                ELSE 4
            END")
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function statusPayload(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'progress' => (int) ($this->progress ?? 0),
            'processed_rows' => (int) ($this->processed_rows ?? 0),
            'total_rows' => (int) ($this->total_rows ?? 0),
            'matched_count' => (int) ($this->matched_count ?? 0),
            'ambiguous_count' => (int) ($this->ambiguous_count ?? 0),
            'not_found_count' => (int) ($this->not_found_count ?? 0),
            'duplicate_count' => (int) ($this->duplicate_count ?? 0),
            'error_message' => $this->error_message,
            'ready' => $this->isDraft(),
            'failed' => $this->isFailed(),
            'label' => $this->statusLabel(),
        ];
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
