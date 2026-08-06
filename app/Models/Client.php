<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Client extends Model
{
    public const STATUSES = [
        'actif' => 'Actif',
        'inactif' => 'Inactif',
        'bloque' => 'Bloqué',
    ];

    public const CATEGORIES = [
        'nouveau' => 'Nouveau client',
        'regulier' => 'Client régulier',
        'premium' => 'Premium',
        'vip' => 'VIP',
    ];

    public const ACQUISITION_SOURCES = [
        'site_web' => 'Site web',
        'whatsapp' => 'WhatsApp',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'recommandation' => 'Recommandation',
        'magasin' => 'Magasin',
        'autre' => 'Autre',
    ];

    public const PAYMENT_METHODS = [
        'especes' => 'Espèces',
        'virement' => 'Virement bancaire',
        'cheque' => 'Chèque',
        'carte' => 'Carte bancaire',
        'paiement_mobile' => 'Paiement mobile',
    ];

    public const DELIVERY_MODES = [
        'domicile' => 'Livraison à domicile',
        'retrait' => 'Retrait en magasin',
        'point_relais' => 'Point relais',
        'express' => 'Livraison express',
    ];

    public const PURCHASE_FREQUENCIES = [
        'occasionnelle' => 'Occasionnelle',
        'reguliere' => 'Régulière',
        'frequente' => 'Fréquente',
        'hebdomadaire' => 'Hebdomadaire',
        'mensuelle' => 'Mensuelle',
    ];

    public const REGIONS = [
        'Tanger-Tétouan-Al Hoceïma',
        'Oriental',
        'Fès-Meknès',
        'Rabat-Salé-Kénitra',
        'Béni Mellal-Khénifra',
        'Casablanca-Settat',
        'Marrakech-Safi',
        'Drâa-Tafilalet',
        'Souss-Massa',
        'Guelmim-Oued Noun',
        'Laâyoune-Sakia El Hamra',
        'Dakhla-Oued Ed-Dahab',
    ];

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'whatsapp',
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
        'client_type',
        'status',
        'date_of_birth',
        'cin',
        'cin_issue_city',
        'rc',
        'notes',
        'category',
        'acquisition_source',
        'discount_percent',
        'loyalty_points',
        'is_vip',
        'preferred_payment_method',
        'preferred_delivery_mode',
        'currency',
        'purchase_frequency',
        'order_ceiling',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'discount_percent' => 'decimal:2',
        'loyalty_points' => 'integer',
        'is_vip' => 'boolean',
        'order_ceiling' => 'decimal:2',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function selectLabel(): string
    {
        return $this->name . ($this->email ? ' (' . $this->email . ')' : '');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    public function posSales()
    {
        return $this->hasMany(PosSale::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ($this->status ?: '—');
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'actif' => 'bg-emerald-100 text-emerald-800',
            'inactif' => 'bg-gray-100 text-gray-700',
            'bloque' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    protected static function booted(): void
    {
        static::deleting(function (Client $client) {
            foreach ($client->documents as $document) {
                Storage::disk('public')->delete($document->path);
                $document->delete();
            }
        });
    }
}
