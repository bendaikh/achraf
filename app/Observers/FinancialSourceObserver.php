<?php

namespace App\Observers;

use App\Models\Expense;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use App\Models\SupplierInvoicePayment;
use App\Models\SupplierPayment;
use App\Services\FinancialMovementService;

class FinancialSourceObserver
{
    public function __construct(
        private FinancialMovementService $movements
    ) {}

    public function created(InvoicePayment|SupplierInvoicePayment|SupplierPayment|Expense|PosSale $model): void
    {
        $this->sync($model);
    }

    public function updated(InvoicePayment|SupplierInvoicePayment|SupplierPayment|Expense|PosSale $model): void
    {
        $this->sync($model);
    }

    public function deleted(InvoicePayment|SupplierInvoicePayment|SupplierPayment|Expense|PosSale $model): void
    {
        $this->movements->deleteForSource($model);
    }

    private function sync(InvoicePayment|SupplierInvoicePayment|SupplierPayment|Expense|PosSale $model): void
    {
        match (true) {
            $model instanceof InvoicePayment => $this->movements->syncFromInvoicePayment($model),
            $model instanceof SupplierInvoicePayment => $this->movements->syncFromSupplierPayment($model),
            $model instanceof SupplierPayment => $this->movements->syncFromSupplierAdvance($model),
            $model instanceof Expense => $this->movements->syncFromExpense($model),
            $model instanceof PosSale => $this->movements->syncFromPosSale($model),
        };
    }
}
