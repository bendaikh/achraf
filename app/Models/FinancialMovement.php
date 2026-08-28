<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialMovement extends Model
{
    public const TYPE_ENTREE = 'entree';

    public const TYPE_SORTIE = 'sortie';

    public const TYPE_VIREMENT = 'virement';

    public const STATUS_BROUILLON = 'brouillon';

    public const STATUS_VALIDE = 'valide';

    public const STATUS_POINTE = 'pointe';

    public const STATUS_CLOTURE = 'cloture';

    public const ACCOUNT_CAISSE = 'caisse';

    public const ACCOUNT_BANQUE = 'banque';

    public const ACCOUNT_OTHER = 'other';

    public const ORIGIN_VENTE = 'vente';

    public const ORIGIN_ACHAT = 'achat';

    public const ORIGIN_DEPENSE = 'depense';

    public const ORIGIN_FOURNISSEUR = 'fournisseur';

    public const ORIGIN_CLIENT = 'client';

    public const ORIGIN_POS = 'pos';

    public const ORIGIN_SHOPIFY = 'shopify';

    public const ORIGIN_JUMIA = 'jumia';

    public const ORIGIN_REMBOURSEMENT = 'remboursement';

    public const ORIGIN_MANUEL = 'manuel';

    public const ORIGIN_SALAIRE = 'salaire';

    public const ORIGIN_LOYER = 'loyer';

    public const ORIGIN_UTILITIES = 'utilities';

    public const ORIGIN_BANQUE = 'banque';

    public const ORIGIN_DIVERS = 'divers';

    protected $fillable = [
        'reference',
        'movement_date',
        'origin',
        'type',
        'label',
        'account',
        'amount_in',
        'amount_out',
        'status',
        'is_manual',
        'source_type',
        'source_id',
        'user_id',
        'justificatif_path',
        'notes',
        'pointed_at',
        'pointed_by',
        'day_closed_at',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'amount_in' => 'decimal:2',
        'amount_out' => 'decimal:2',
        'is_manual' => 'boolean',
        'pointed_at' => 'datetime',
        'day_closed_at' => 'datetime',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pointedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pointed_by');
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_CLOTURE || $this->day_closed_at !== null;
    }

    public function isEditable(): bool
    {
        return $this->is_manual && ! $this->isLocked();
    }

    public function isDeletable(): bool
    {
        return $this->is_manual && ! $this->isLocked() && $this->status !== self::STATUS_POINTE;
    }

    public function netAmount(): float
    {
        return round((float) $this->amount_in - (float) $this->amount_out, 2);
    }

    public static function originLabels(): array
    {
        return [
            self::ORIGIN_VENTE => 'Vente',
            self::ORIGIN_ACHAT => 'Achat',
            self::ORIGIN_DEPENSE => 'Dépense',
            self::ORIGIN_FOURNISSEUR => 'Fournisseur',
            self::ORIGIN_CLIENT => 'Client',
            self::ORIGIN_POS => 'POS',
            self::ORIGIN_SHOPIFY => 'Shopify',
            self::ORIGIN_JUMIA => 'Jumia',
            self::ORIGIN_REMBOURSEMENT => 'Remboursement client',
            self::ORIGIN_MANUEL => 'Manuel',
            self::ORIGIN_SALAIRE => 'Salaire',
            self::ORIGIN_LOYER => 'Loyer',
            self::ORIGIN_UTILITIES => 'Eau / Électricité / Internet',
            self::ORIGIN_BANQUE => 'Banque',
            self::ORIGIN_DIVERS => 'Divers',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_ENTREE => 'Entrée',
            self::TYPE_SORTIE => 'Sortie',
            self::TYPE_VIREMENT => 'Virement',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_BROUILLON => 'Brouillon',
            self::STATUS_VALIDE => 'Validé',
            self::STATUS_POINTE => 'Pointé',
            self::STATUS_CLOTURE => 'Clôturé',
        ];
    }

    public static function accountLabels(): array
    {
        return [
            self::ACCOUNT_CAISSE => 'Caisse',
            self::ACCOUNT_BANQUE => 'Banque',
            self::ACCOUNT_OTHER => 'Autre',
        ];
    }
}
