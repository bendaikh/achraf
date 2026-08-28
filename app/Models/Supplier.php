<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Supplier extends Model
{
    public const STATUSES = [
        'actif' => 'Actif',
        'inactif' => 'Inactif',
        'bloque' => 'Bloqué',
    ];

    public const PAYMENT_METHODS = [
        'especes' => 'Espèces',
        'virement' => 'Virement',
        'cheque' => 'Chèque',
    ];

    public const PAYMENT_TERMS = [
        'comptant' => 'Comptant',
        '30_jours' => '30 jours',
        '60_jours' => '60 jours',
        '90_jours' => '90 jours',
    ];

    public const LEGAL_FORMS = [
        'SARL' => 'SARL',
        'SARL AU' => 'SARL AU',
        'SA' => 'SA',
        'SNC' => 'SNC',
        'SAS' => 'SAS',
        'Auto-entrepreneur' => 'Auto-entrepreneur',
        'Autre' => 'Autre',
    ];

    public const DOCUMENT_FIELDS = [
        'rc_document_path' => 'Registre de Commerce',
        'ice_attestation_path' => 'Attestation ICE',
        'if_attestation_path' => 'Attestation IF',
        'rib_document_path' => 'RIB bancaire',
        'contract_path' => 'Contrat fournisseur',
        'catalog_path' => 'Catalogue',
        'price_list_path' => 'Liste de prix',
    ];

    protected $fillable = [
        'name',
        'legal_name',
        'trade_name',
        'email',
        'phone',
        'whatsapp',
        'website',
        'address',
        'city',
        'country',
        'tax_id',
        'code',
        'postal_code',
        'region',
        'ice',
        'fiscal_identifier',
        'latitude',
        'longitude',
        'ville',
        'rc',
        'rc_city',
        'tp',
        'legal_form',
        'company_created_at',
        'contact_name',
        'contact_role',
        'contact_phone',
        'contact_mobile',
        'contact_email',
        'bank_name',
        'bank_account_holder',
        'rib',
        'iban',
        'swift_bic',
        'payment_method',
        'payment_terms',
        'currency',
        'discount_percent',
        'min_order_amount',
        'delivery_lead_days',
        'status',
        'category',
        'internal_owner_id',
        'notes',
        'rc_document_path',
        'ice_attestation_path',
        'if_attestation_path',
        'rib_document_path',
        'contract_path',
        'catalog_path',
        'price_list_path',
    ];

    protected function casts(): array
    {
        return [
            'company_created_at' => 'date',
            'discount_percent' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'delivery_lead_days' => 'integer',
        ];
    }

    public function selectLabel(): string
    {
        return $this->name . ($this->email ? ' (' . $this->email . ')' : '');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ($this->status ?: 'Actif');
    }

    public function paymentMethodLabel(): ?string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    public function paymentTermsLabel(): ?string
    {
        return self::PAYMENT_TERMS[$this->payment_terms] ?? $this->payment_terms;
    }

    public function documentUrl(string $field): ?string
    {
        $path = $this->{$field} ?? null;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function internalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_owner_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(SupplierPurchaseOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(SupplierCreditNote::class);
    }

    public function accountPayments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function receptions()
    {
        return $this->hasMany(Reception::class);
    }
}
