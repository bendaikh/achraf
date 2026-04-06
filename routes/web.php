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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    
    Route::resource('products', ProductController::class);

    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/{product}/edit', [StockController::class, 'edit'])->name('stock.edit');
    Route::patch('/stock/{product}', [StockController::class, 'update'])->name('stock.update');
    
    Route::prefix('sales')->group(function () {
        Route::resource('invoices', InvoiceController::class);
        Route::resource('quotes', QuoteController::class);
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
});
