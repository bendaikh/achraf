<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\ExpenseController;
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
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    
    Route::resource('products', ProductController::class);
    Route::post('/products/sync-shopify', [ProductController::class, 'syncShopify'])->name('products.sync-shopify');

    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/{product}/edit', [StockController::class, 'edit'])->name('stock.edit');
    Route::patch('/stock/{product}', [StockController::class, 'update'])->name('stock.update');
    
    Route::prefix('crm')->group(function () {
        Route::resource('clients', ClientController::class);
        Route::resource('suppliers', SupplierController::class);
    });
    
    Route::prefix('sales')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::resource('invoices', InvoiceController::class);
        Route::resource('quotes', QuoteController::class);
        Route::get('purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->name('purchase-orders.print');
        Route::resource('purchase-orders', PurchaseOrderController::class);
        Route::resource('credit-notes', CreditNoteController::class);
    });

    Route::prefix('purchases')->group(function () {
        Route::resource('expenses', ExpenseController::class);
        Route::resource('supplier-purchase-orders', SupplierPurchaseOrderController::class);
        Route::resource('receptions', ReceptionController::class);
        Route::resource('supplier-invoices', SupplierInvoiceController::class);
        Route::resource('supplier-credit-notes', SupplierCreditNoteController::class);
    });

    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PointOfSaleController::class, 'index'])->name('index');
        Route::get('/products/search', [PointOfSaleController::class, 'searchProducts'])->name('products.search');
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
});
