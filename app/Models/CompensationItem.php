<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompensationItem extends Model
{
    public const KIND_PRIME_EXCEPTIONNELLE = 'prime_exceptionnelle';

    public const KIND_PRIME_MENSUELLE = 'prime_mensuelle';

    public const KIND_PRIME_PERFORMANCE = 'prime_performance';

    public const KIND_INDEMNITE_TRANSPORT = 'indemnite_transport';

    public const KIND_INDEMNITE_DEPLACEMENT = 'indemnite_deplacement';

    public const KIND_INDEMNITE_REPAS = 'indemnite_repas';

    public const KIND_AUTRE = 'autre';

    public const KINDS = [
        self::KIND_PRIME_EXCEPTIONNELLE => 'Prime exceptionnelle',
        self::KIND_PRIME_MENSUELLE => 'Prime mensuelle',
        self::KIND_PRIME_PERFORMANCE => 'Prime de rendement',
        self::KIND_INDEMNITE_TRANSPORT => 'Indemnité de transport',
        self::KIND_INDEMNITE_DEPLACEMENT => 'Indemnité de déplacement',
        self::KIND_INDEMNITE_REPAS => 'Indemnité repas',
        self::KIND_AUTRE => 'Élément personnalisé',
    ];

    public const RECURRENCE_PONCTUEL = 'ponctuel';

    public const RECURRENCE_RECURRENT = 'recurrent';

    public const RECURRENCE_PERIODIQUE = 'periodique';

    public const RECURRENCES = [
        self::RECURRENCE_PONCTUEL => 'Ponctuel',
        self::RECURRENCE_RECURRENT => 'Mensuel',
        self::RECURRENCE_PERIODIQUE => 'Récurrent jusqu\'à une date',
    ];

    public const STATUS_ACTIF = 'actif';

    public const STATUS_SUSPENDU = 'suspendu';

    public const STATUS_ARRETE = 'arrete';

    public const STATUSES = [
        self::STATUS_ACTIF => 'Actif',
        self::STATUS_SUSPENDU => 'Suspendu',
        self::STATUS_ARRETE => 'Arrêté',
    ];

    protected $fillable = [
        'employee_id',
        'kind',
        'recurrence',
        'status',
        'amount',
        'start_date',
        'end_date',
        'status_effective_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'status_effective_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function recurrenceLabel(): string
    {
        return self::RECURRENCES[$this->recurrence] ?? $this->recurrence;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status ?? self::STATUS_ACTIF] ?? ($this->status ?? 'Actif');
    }

    public function isPrime(): bool
    {
        return str_starts_with($this->kind, 'prime_');
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIF) === self::STATUS_ACTIF;
    }

    public function appliesTo(\DateTimeInterface $from, \DateTimeInterface $to): bool
    {
        $start = $this->start_date?->startOfDay();
        $end = $this->end_date?->endOfDay();
        $periodStart = \Carbon\Carbon::parse($from)->startOfDay();
        $periodEnd = \Carbon\Carbon::parse($to)->endOfDay();

        if ($start && $start->gt($periodEnd)) {
            return false;
        }

        if ($end && $end->lt($periodStart)) {
            return false;
        }

        if ($this->recurrence === self::RECURRENCE_PONCTUEL) {
            return $start && $start->betweenIncluded($periodStart, $periodEnd);
        }

        return true;
    }
}
