<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseWithInvoiceController;
use App\Http\Controllers\ExpenseWithoutInvoiceController;
use App\Http\Controllers\SupplierInvoicePaymentController;
use App\Http\Controllers\SupplierPurchaseOrderController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\SupplierCreditNoteController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\PointOfSaleController;
use App\Http\Controllers\PosSaleController;
use App\Http\Controllers\ShopifyIntegrationController;
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TableExportController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\DocumentImportController;
use App\Http\Controllers\CrmImportController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Shopify Webhooks (no CSRF verification - handled by HMAC)
Route::post('/api/webhooks/shopify/orders/create', [ShopifyWebhookController::class, 'ordersCreate'])
    ->name('webhooks.shopify.orders.create');
Route::post('/api/webhooks/shopify/orders/updated', [ShopifyWebhookController::class, 'ordersUpdated'])
    ->name('webhooks.shopify.orders.updated');
Route::post('/api/webhooks/shopify/products/create', [ShopifyWebhookController::class, 'productsCreate'])
    ->name('webhooks.shopify.products.create');
Route::post('/api/webhooks/shopify/products/update', [ShopifyWebhookController::class, 'productsUpdate'])
    ->name('webhooks.shopify.products.update');
Route::post('/api/webhooks/shopify/products/delete', [ShopifyWebhookController::class, 'productsDelete'])
    ->name('webhooks.shopify.products.delete');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::resource('products', ProductController::class);
    Route::post('/products/sync-shopify', [ProductController::class, 'syncShopify'])->name('products.sync-shopify');
    Route::post('/products/{product}/duplicate-to-manual', [ProductController::class, 'duplicateToManual'])->name('products.duplicate-to-manual');

    Route::prefix('stock')->group(function () {
        Route::get('/enligne', [StockController::class, 'indexEnligne'])->name('stock.enligne.index');
        Route::get('/enligne/export/{format}', [StockReportController::class, 'exportEnligne'])->name('stock.enligne.export');
        Route::get('/enligne/{product}/edit', [StockController::class, 'editEnligne'])->name('stock.enligne.edit');
        Route::patch('/enligne/{product}', [StockController::class, 'updateEnligne'])->name('stock.enligne.update');
        
        Route::get('/magasin', [StockController::class, 'indexMagasin'])->name('stock.magasin.index');
        Route::get('/magasin/export/{format}', [StockReportController::class, 'exportMagasin'])->name('stock.magasin.export');
        Route::get('/magasin/{product}/edit', [StockController::class, 'editMagasin'])->name('stock.magasin.edit');
        Route::patch('/magasin/{product}', [StockController::class, 'updateMagasin'])->name('stock.magasin.update');
    });
    
    Route::prefix('crm')->group(function () {
        Route::get('clients/import/template', [CrmImportController::class, 'clientTemplate'])->name('clients.import.template');
        Route::post('clients/import', [CrmImportController::class, 'importClients'])->name('clients.import');
        Route::get('suppliers/import/template', [CrmImportController::class, 'supplierTemplate'])->name('suppliers.import.template');
        Route::post('suppliers/import', [CrmImportController::class, 'importSuppliers'])->name('suppliers.import');
        Route::resource('clients', ClientController::class);
        Route::resource('suppliers', SupplierController::class);
    });
    
    Route::prefix('sales')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/bulk-convert', [OrderController::class, 'bulkConvert'])->name('orders.bulk-convert');
        Route::get('invoices/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'invoices')->name('invoices.import.template');
        Route::post('invoices/import', [DocumentImportController::class, 'import'])->defaults('type', 'invoices')->name('invoices.import');
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::patch('invoices/{invoice}/payment-status', [InvoiceController::class, 'updatePaymentStatus'])->name('invoices.payment-status');
        Route::resource('invoices', InvoiceController::class);
        Route::get('quotes/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'quotes')->name('quotes.import.template');
        Route::post('quotes/import', [DocumentImportController::class, 'import'])->defaults('type', 'quotes')->name('quotes.import');
        Route::get('quotes/{quote}/print', [QuoteController::class, 'print'])->name('quotes.print');
        Route::get('quotes/{quote}/pdf', [QuoteController::class, 'downloadPdf'])->name('quotes.pdf');
        Route::resource('quotes', QuoteController::class);
        Route::get('purchase-orders/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'purchase-orders')->name('purchase-orders.import.template');
        Route::post('purchase-orders/import', [DocumentImportController::class, 'import'])->defaults('type', 'purchase-orders')->name('purchase-orders.import');
        Route::get('purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->name('purchase-orders.print');
        Route::get('purchase-orders/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'downloadPdf'])->name('purchase-orders.pdf');
        Route::resource('purchase-orders', PurchaseOrderController::class);
        Route::get('credit-notes/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'credit-notes')->name('credit-notes.import.template');
        Route::post('credit-notes/import', [DocumentImportController::class, 'import'])->defaults('type', 'credit-notes')->name('credit-notes.import');
        Route::get('credit-notes/{creditNote}/print', [CreditNoteController::class, 'print'])->name('credit-notes.print');
        Route::get('credit-notes/{creditNote}/pdf', [CreditNoteController::class, 'downloadPdf'])->name('credit-notes.pdf');
        Route::resource('credit-notes', CreditNoteController::class);
    });

    Route::prefix('purchases')->group(function () {
        Route::resource('expenses', ExpenseController::class);
        Route::resource('expenses-with-invoice', ExpenseWithInvoiceController::class)->parameters(['expenses-with-invoice' => 'expenseWithInvoice']);
        Route::resource('expenses-without-invoice', ExpenseWithoutInvoiceController::class)->parameters(['expenses-without-invoice' => 'expenseWithoutInvoice']);
        Route::resource('supplier-purchase-orders', SupplierPurchaseOrderController::class);
        Route::resource('receptions', ReceptionController::class);
        Route::get('supplier-invoices/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'supplier-invoices')->name('supplier-invoices.import.template');
        Route::post('supplier-invoices/import', [DocumentImportController::class, 'import'])->defaults('type', 'supplier-invoices')->name('supplier-invoices.import');
        Route::resource('supplier-invoices', SupplierInvoiceController::class);
        Route::get('supplier-invoices/{supplierInvoice}/print', [SupplierInvoiceController::class, 'print'])->name('supplier-invoices.print');
        Route::get('supplier-invoices/{supplierInvoice}/pdf', [SupplierInvoiceController::class, 'downloadPdf'])->name('supplier-invoices.pdf');
        Route::get('supplier-invoices/{supplierInvoice}/payments', [SupplierInvoicePaymentController::class, 'index'])->name('supplier-invoices.payments.index');
        Route::post('supplier-invoices/{supplierInvoice}/payments', [SupplierInvoicePaymentController::class, 'store'])->name('supplier-invoices.payments.store');
        Route::delete('supplier-invoices/{supplierInvoice}/payments/{payment}', [SupplierInvoicePaymentController::class, 'destroy'])->name('supplier-invoices.payments.destroy');
        Route::resource('supplier-credit-notes', SupplierCreditNoteController::class);
    });

    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PointOfSaleController::class, 'index'])->name('index');
        Route::get('/products/search', [PointOfSaleController::class, 'searchProducts'])->name('products.search');
        Route::get('/products/catalog', [PointOfSaleController::class, 'catalog'])->name('products.catalog');
        Route::post('/checkout', [PointOfSaleController::class, 'checkout'])->name('checkout');
        Route::get('/sales', [PosSaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [PosSaleController::class, 'show'])->name('sales.show');
    });

    Route::get('/integrations/shopify', [ShopifyIntegrationController::class, 'edit'])->name('integrations.shopify.edit');
    Route::put('/integrations/shopify', [ShopifyIntegrationController::class, 'update'])->name('integrations.shopify.update');
    Route::post('/integrations/shopify/sync', [ShopifyIntegrationController::class, 'sync'])->name('integrations.shopify.sync');
    Route::delete('/integrations/shopify', [ShopifyIntegrationController::class, 'destroy'])->name('integrations.shopify.destroy');
    Route::get('/integrations/shopify/install', [ShopifyIntegrationController::class, 'install'])->name('integrations.shopify.install');
    Route::get('/integrations/shopify/callback', [ShopifyIntegrationController::class, 'callback'])->name('integrations.shopify.callback');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::post('/export/table', [TableExportController::class, 'export'])->name('table.export');
});
