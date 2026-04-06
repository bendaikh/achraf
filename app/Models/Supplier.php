<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'address', 'city', 'country', 'tax_id',
    ];

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

    public function receptions()
    {
        return $this->hasMany(Reception::class);
    }
}
