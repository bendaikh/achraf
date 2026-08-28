<?php

namespace App\Providers;

use App\Models\ClientRefund;
use App\Models\Expense;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use App\Models\SupplierInvoicePayment;
use App\Models\SupplierPayment;
use App\Observers\FinancialSourceObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        InvoicePayment::observe(FinancialSourceObserver::class);
        SupplierInvoicePayment::observe(FinancialSourceObserver::class);
        SupplierPayment::observe(FinancialSourceObserver::class);
        Expense::observe(FinancialSourceObserver::class);
        PosSale::observe(FinancialSourceObserver::class);
        ClientRefund::observe(FinancialSourceObserver::class);
    }
}
