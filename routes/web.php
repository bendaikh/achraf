<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientRefundController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\CrmImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\DocumentArchiveExportController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\DocumentImportController;
use App\Http\Controllers\ManagedDocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseRecurrenceController;
use App\Http\Controllers\ExpenseWithInvoiceController;
use App\Http\Controllers\ExpenseWithoutInvoiceController;
use App\Http\Controllers\FinancialManagementController;
use App\Http\Controllers\FinancialMovementController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\JumiaIntegrationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PointOfSaleController;
use App\Http\Controllers\PosSaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchasePaymentController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\SalesPaymentController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShopifyIntegrationController;
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\LocationStockController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockReplenishmentController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierCreditNoteController;
use App\Http\Controllers\SupplierDeliveryNoteController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\SupplierInvoicePaymentController;
use App\Http\Controllers\SupplierPurchaseOrderController;
use App\Http\Controllers\TableBulkDestroyController;
use App\Http\Controllers\TableColumnPreferenceController;
use App\Http\Controllers\TableExportController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\CompensationController;
use App\Http\Controllers\Hr\EmployeeContractController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\EmployeeExitController;
use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\Hr\HrDocumentController;
use App\Http\Controllers\Hr\HrHistoryController;
use App\Http\Controllers\Hr\HrImportController;
use App\Http\Controllers\Hr\HrReportController;
use App\Http\Controllers\Hr\HrSettingsController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\PayrollController;
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
Route::post('/api/webhooks/shopify/orders/fulfilled', [ShopifyWebhookController::class, 'ordersUpdated'])
    ->name('webhooks.shopify.orders.fulfilled');
Route::post('/api/webhooks/shopify/orders/partially-fulfilled', [ShopifyWebhookController::class, 'ordersUpdated'])
    ->name('webhooks.shopify.orders.partially_fulfilled');
Route::post('/api/webhooks/shopify/refunds/create', [ShopifyWebhookController::class, 'refundsCreate'])
    ->name('webhooks.shopify.refunds.create');
Route::post('/api/webhooks/shopify/fulfillments/create', [ShopifyWebhookController::class, 'fulfillmentsCreate'])
    ->name('webhooks.shopify.fulfillments.create');
Route::post('/api/webhooks/shopify/fulfillments/update', [ShopifyWebhookController::class, 'fulfillmentsUpdate'])
    ->name('webhooks.shopify.fulfillments.update');
Route::post('/api/webhooks/shopify/products/create', [ShopifyWebhookController::class, 'productsCreate'])
    ->name('webhooks.shopify.products.create');
Route::post('/api/webhooks/shopify/products/update', [ShopifyWebhookController::class, 'productsUpdate'])
    ->name('webhooks.shopify.products.update');
Route::post('/api/webhooks/shopify/products/delete', [ShopifyWebhookController::class, 'productsDelete'])
    ->name('webhooks.shopify.products.delete');
Route::post('/api/webhooks/shopify/inventory-levels/update', [ShopifyWebhookController::class, 'inventoryLevelsUpdate'])
    ->name('webhooks.shopify.inventory_levels.update');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/financial', [FinancialManagementController::class, 'index'])->name('financial.index');
    Route::get('/financial/tva', [FinancialManagementController::class, 'tva'])->name('financial.tva');
    Route::post('/financial/tva/control', [FinancialManagementController::class, 'tvaControl'])->name('financial.tva.control');
    Route::get('/financial/tva/prepare', [FinancialManagementController::class, 'tvaPrepare'])->name('financial.tva.prepare');
    Route::post('/financial/tva/pieces', [FinancialMovementController::class, 'storePiece'])->name('financial.tva.pieces.store');
    Route::get('/financial/tresorerie', [FinancialManagementController::class, 'tresorerie'])->name('financial.tresorerie');
    Route::post('/financial/tresorerie/close-day', [FinancialMovementController::class, 'closeDay'])->name('financial.tresorerie.close-day');
    Route::get('/financial/achats-depenses', [FinancialManagementController::class, 'achatsDepenses'])->name('financial.achats-depenses');
    Route::get('/financial/creances-dettes', [FinancialManagementController::class, 'creancesDettes'])->name('financial.creances-dettes');
    Route::post('/financial/creances-dettes/relancer', [FinancialManagementController::class, 'relancer'])->name('financial.creances-dettes.relancer');
    Route::get('/financial/declarations', [FinancialManagementController::class, 'declarations'])->name('financial.declarations');
    Route::post('/financial/declarations/control', [FinancialManagementController::class, 'declarationsControl'])->name('financial.declarations.control');
    Route::post('/financial/declarations/validate', [FinancialManagementController::class, 'declarationsValidate'])->name('financial.declarations.validate');
    Route::post('/financial/declarations/close', [FinancialManagementController::class, 'declarationsClose'])->name('financial.declarations.close');
    Route::post('/financial/declarations/reopen', [FinancialManagementController::class, 'declarationsReopen'])->name('financial.declarations.reopen');
    Route::get('/financial/export', [FinancialManagementController::class, 'export'])->name('financial.export');

    Route::get('/financial/mouvements', [FinancialMovementController::class, 'index'])->name('financial.mouvements.index');
    Route::get('/financial/mouvements/create', [FinancialMovementController::class, 'create'])->name('financial.mouvements.create');
    Route::post('/financial/mouvements', [FinancialMovementController::class, 'store'])->name('financial.mouvements.store');
    Route::get('/financial/mouvements/reconcile', [FinancialMovementController::class, 'reconcile'])->name('financial.mouvements.reconcile');
    Route::post('/financial/mouvements/sync', [FinancialMovementController::class, 'sync'])->name('financial.mouvements.sync');
    Route::post('/financial/mouvements/point-bulk', [FinancialMovementController::class, 'pointBulk'])->name('financial.mouvements.point-bulk');
    Route::get('/financial/mouvements/export', [FinancialMovementController::class, 'export'])->name('financial.mouvements.export');
    Route::get('/financial/mouvements/{mouvement}/edit', [FinancialMovementController::class, 'edit'])->name('financial.mouvements.edit');
    Route::put('/financial/mouvements/{mouvement}', [FinancialMovementController::class, 'update'])->name('financial.mouvements.update');
    Route::delete('/financial/mouvements/{mouvement}', [FinancialMovementController::class, 'destroy'])->name('financial.mouvements.destroy');
    Route::post('/financial/mouvements/{mouvement}/point', [FinancialMovementController::class, 'point'])->name('financial.mouvements.point');
    Route::post('/document-files/{type}/{id}', [DocumentFileController::class, 'store'])->name('document-files.store');
    Route::post('/managed-documents/{managedDocument}/replace', [ManagedDocumentController::class, 'replace'])->name('managed-documents.replace');
    Route::delete('/managed-documents/{managedDocument}', [ManagedDocumentController::class, 'destroy'])->name('managed-documents.destroy');
    Route::get('/managed-documents/{managedDocument}', [ManagedDocumentController::class, 'show'])->name('managed-documents.show');
    Route::get('/managed-documents/{managedDocument}/download', [ManagedDocumentController::class, 'download'])->name('managed-documents.download');
    Route::get('/managed-documents/{managedDocument}/history', [ManagedDocumentController::class, 'history'])->name('managed-documents.history');
    Route::get('/documents/archive', [DocumentArchiveExportController::class, 'index'])->name('documents.archive.index');
    Route::post('/documents/archive/export', [DocumentArchiveExportController::class, 'export'])->name('documents.archive.export');

    Route::get('/catalog/products/search', [ProductController::class, 'searchForSelect'])->name('catalog.products.search');
    Route::resource('products', ProductController::class);
    Route::get('/products-categories', [ProductController::class, 'categories'])->name('products.categories');
    Route::put('/products-categories', [ProductController::class, 'updateCategories'])->name('products.categories.update');
    Route::post('/products/sync-shopify', [ProductController::class, 'syncShopify'])->name('products.sync-shopify');
    Route::get('/products/{product}/purchase-history', [ProductController::class, 'purchaseHistory'])->name('products.purchase-history');
    Route::post('/products/{product}/duplicate-to-manual', [ProductController::class, 'duplicateToManual'])->name('products.duplicate-to-manual');
    Route::post('/products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
    Route::post('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
    Route::post('/products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');

    Route::prefix('stock')->group(function () {
        Route::get('/', [StockController::class, 'index'])->name('stock.index');
        Route::get('/inventory', [StockController::class, 'inventory'])->name('stock.inventory.index');
        Route::get('/alerts', [StockController::class, 'alerts'])->name('stock.alerts.index');
        Route::get('/movements', [StockController::class, 'movements'])->name('stock.movements.index');
        Route::get('/transfer', [StockController::class, 'transferForm'])->name('stock.transfer.create');
        Route::post('/transfer', [StockController::class, 'transferStore'])->name('stock.transfer.store');

        Route::get('/enligne', [StockController::class, 'indexEnligne'])->name('stock.enligne.index');
        Route::get('/enligne/export/{format}', [StockReportController::class, 'exportEnligne'])->name('stock.enligne.export');
        Route::get('/enligne/{product}/edit', [StockController::class, 'editEnligne'])->name('stock.enligne.edit');
        Route::patch('/enligne/{product}', [StockController::class, 'updateEnligne'])->name('stock.enligne.update');

        Route::get('/magasin', [StockController::class, 'indexMagasin'])->name('stock.magasin.index');
        Route::get('/magasin/export/{format}', [StockReportController::class, 'exportMagasin'])->name('stock.magasin.export');
        Route::get('/magasin/{product}/edit', [StockController::class, 'editMagasin'])->name('stock.magasin.edit');
        Route::get('/magasin/{product}/slot-quantity', [StockController::class, 'slotQuantity'])->name('stock.magasin.slot-quantity');
        Route::patch('/magasin/{product}', [StockController::class, 'updateMagasin'])->name('stock.magasin.update');

        Route::get('/locations', [LocationStockController::class, 'index'])->name('stock.locations.index');
        Route::get('/locations/{warehouse}', [LocationStockController::class, 'show'])->name('stock.locations.show');
        Route::get('/locations/{warehouse}/export/{format}', [LocationStockController::class, 'export'])->name('stock.locations.export');
        Route::get('/locations/{warehouse}/count', [LocationStockController::class, 'countForm'])->name('stock.locations.count');
        Route::post('/locations/{warehouse}/count', [LocationStockController::class, 'countStore'])->name('stock.locations.count.store');

        Route::get('/replenishment', [StockReplenishmentController::class, 'index'])->name('stock.replenishment.index');
        Route::patch('/replenishment/{need}/supplier', [StockReplenishmentController::class, 'updateSupplier'])->name('stock.replenishment.supplier');
        Route::post('/replenishment/generate-po', [StockReplenishmentController::class, 'generatePurchaseOrder'])->name('stock.replenishment.generate-po');
    });

    Route::get('/products/{product}/location-stocks', [LocationStockController::class, 'productBreakdown'])->name('products.location-stocks');
    Route::post('/products/{product}/declare-stock', [LocationStockController::class, 'declarePhysicalStock'])->name('products.declare-stock');

    Route::get('/api/warehouse-locations', [WarehouseController::class, 'locationsJson'])->name('warehouses.locations.json');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    Route::post('/warehouse-locations', [WarehouseController::class, 'storeLocation'])->name('warehouse-locations.store');
    Route::put('/warehouse-locations/{location}', [WarehouseController::class, 'updateLocation'])->name('warehouse-locations.update');
    Route::delete('/warehouse-locations/{location}', [WarehouseController::class, 'destroyLocation'])->name('warehouse-locations.destroy');

    Route::prefix('crm')->group(function () {
        Route::get('clients/import/template', [CrmImportController::class, 'clientTemplate'])->name('clients.import.template');
        Route::post('clients/import', [CrmImportController::class, 'importClients'])->name('clients.import');
        Route::get('suppliers/import/template', [CrmImportController::class, 'supplierTemplate'])->name('suppliers.import.template');
        Route::post('suppliers/import', [CrmImportController::class, 'importSuppliers'])->name('suppliers.import');
        Route::get('clients/search', [ClientController::class, 'search'])->name('clients.search');
        Route::post('clients/quick-store', [ClientController::class, 'quickStore'])->name('clients.quick-store');
        Route::delete('clients/{client}/documents/{document}', [ClientController::class, 'destroyDocument'])->name('clients.documents.destroy');
        Route::resource('clients', ClientController::class);
        Route::get('suppliers/search', [SupplierController::class, 'search'])->name('suppliers.search');
        Route::post('suppliers/quick-store', [SupplierController::class, 'quickStore'])->name('suppliers.quick-store');
        Route::resource('suppliers', SupplierController::class);
    });

    Route::prefix('sales')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::get('orders-products/search', [OrderController::class, 'searchProducts'])->name('orders.products.search');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('orders/{order}/sync-shopify', [OrderController::class, 'sync'])->name('orders.sync-shopify');
        Route::post('orders/{order}/prepare-physical-stock', [OrderController::class, 'preparePhysicalStock'])->name('orders.prepare-physical-stock');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/bulk-convert', [OrderController::class, 'bulkConvert'])->name('orders.bulk-convert');
        Route::get('invoices/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'invoices')->name('invoices.import.template');
        Route::post('invoices/import', [DocumentImportController::class, 'import'])->defaults('type', 'invoices')->name('invoices.import');
        Route::get('invoices/by-client/{client}', [InvoiceController::class, 'byClient'])->name('invoices.by-client');
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::patch('invoices/{invoice}/payment-status', [InvoiceController::class, 'updatePaymentStatus'])->name('invoices.payment-status');
        Route::get('invoices/{invoice}/payments', [InvoicePaymentController::class, 'index'])->name('invoices.payments.index');
        Route::post('invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
        Route::delete('invoices/{invoice}/payments/{payment}', [InvoicePaymentController::class, 'destroy'])->name('invoices.payments.destroy');
        Route::get('payments', [SalesPaymentController::class, 'index'])->name('sales.payments.index');
        Route::post('payments/manual', [SalesPaymentController::class, 'storeManual'])->name('sales.payments.manual');
        Route::get('payments/bulk', [SalesPaymentController::class, 'bulkForm'])->name('sales.payments.bulk');
        Route::post('payments/bulk', [SalesPaymentController::class, 'storeBulk'])->name('sales.payments.bulk.store');
        Route::get('payments/import', [SalesPaymentController::class, 'importForm'])->name('sales.payments.import');
        Route::post('payments/import', [SalesPaymentController::class, 'importStore'])->name('sales.payments.import.store');
        Route::get('payments/import/{paymentImport}', [SalesPaymentController::class, 'importShow'])->name('sales.payments.import.show');
        Route::patch('payments/import/{paymentImport}/lines/{line}', [SalesPaymentController::class, 'importUpdateLine'])->name('sales.payments.import.line');
        Route::post('payments/import/{paymentImport}/validate', [SalesPaymentController::class, 'importValidate'])->name('sales.payments.import.validate');
        Route::get('refunds/create', [ClientRefundController::class, 'create'])->name('sales.refunds.create');
        Route::post('refunds', [ClientRefundController::class, 'store'])->name('sales.refunds.store');
        Route::get('refunds/{refund}', [ClientRefundController::class, 'show'])->name('sales.refunds.show');
        Route::delete('refunds/{refund}', [ClientRefundController::class, 'destroy'])->name('sales.refunds.destroy');
        Route::get('refunds', [ClientRefundController::class, 'index'])->name('sales.refunds.index');
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
        Route::get('delivery-notes/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'delivery-notes')->name('delivery-notes.import.template');
        Route::post('delivery-notes/import', [DocumentImportController::class, 'import'])->defaults('type', 'delivery-notes')->name('delivery-notes.import');
        Route::get('delivery-notes/{deliveryNote}/print', [DeliveryNoteController::class, 'print'])->name('delivery-notes.print');
        Route::get('delivery-notes/{deliveryNote}/pdf', [DeliveryNoteController::class, 'downloadPdf'])->name('delivery-notes.pdf');
        Route::resource('delivery-notes', DeliveryNoteController::class);
        Route::get('credit-notes/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'credit-notes')->name('credit-notes.import.template');
        Route::post('credit-notes/import', [DocumentImportController::class, 'import'])->defaults('type', 'credit-notes')->name('credit-notes.import');
        Route::get('credit-notes/{creditNote}/print', [CreditNoteController::class, 'print'])->name('credit-notes.print');
        Route::get('credit-notes/{creditNote}/pdf', [CreditNoteController::class, 'downloadPdf'])->name('credit-notes.pdf');
        Route::resource('credit-notes', CreditNoteController::class);
    });

    Route::prefix('purchases')->group(function () {
        Route::post('expense-occurrences/{expense}/mark-paid', [ExpenseRecurrenceController::class, 'markPaid'])->name('expenses.mark-paid');
        Route::post('expense-recurrences/{expense}/suspend', [ExpenseRecurrenceController::class, 'suspend'])->name('expenses.recurrence.suspend');
        Route::post('expense-recurrences/{expense}/resume', [ExpenseRecurrenceController::class, 'resume'])->name('expenses.recurrence.resume');
        Route::post('expense-recurrences/{expense}/stop', [ExpenseRecurrenceController::class, 'stop'])->name('expenses.recurrence.stop');
        Route::get('payments', [PurchasePaymentController::class, 'index'])->name('purchases.payments.index');
        Route::get('payments/history', [PurchasePaymentController::class, 'history'])->name('purchases.payments.history');
        Route::post('payments/manual', [PurchasePaymentController::class, 'storeManual'])->name('purchases.payments.manual');
        Route::get('payments/settle/{supplier}', [PurchasePaymentController::class, 'settle'])->name('purchases.payments.settle');
        Route::post('payments/settle/{supplier}', [PurchasePaymentController::class, 'storeSettlement'])->name('purchases.payments.settle.store');
        Route::get('payments/bulk', [PurchasePaymentController::class, 'bulkForm'])->name('purchases.payments.bulk');
        Route::post('payments/bulk', [PurchasePaymentController::class, 'storeBulk'])->name('purchases.payments.bulk.store');
        Route::get('payments/import', [PurchasePaymentController::class, 'importForm'])->name('purchases.payments.import');
        Route::post('payments/import', [PurchasePaymentController::class, 'importStore'])->name('purchases.payments.import.store');
        Route::get('payments/import/{paymentImport}', [PurchasePaymentController::class, 'importShow'])->name('purchases.payments.import.show');
        Route::patch('payments/import/{paymentImport}/lines/{line}', [PurchasePaymentController::class, 'importUpdateLine'])->name('purchases.payments.import.line');
        Route::post('payments/import/{paymentImport}/validate', [PurchasePaymentController::class, 'importValidate'])->name('purchases.payments.import.validate');
        Route::get('payments/{payment}', [PurchasePaymentController::class, 'show'])->name('purchases.payments.show');
        Route::get('payments/{payment}/edit', [PurchasePaymentController::class, 'edit'])->name('purchases.payments.edit');
        Route::put('payments/{payment}', [PurchasePaymentController::class, 'update'])->name('purchases.payments.update');
        Route::post('payments/{payment}/cancel', [PurchasePaymentController::class, 'cancel'])->name('purchases.payments.cancel');
        Route::get('payments/{payment}/print', [PurchasePaymentController::class, 'print'])->name('purchases.payments.print');
        Route::get('payments/{payment}/pdf', [PurchasePaymentController::class, 'downloadPdf'])->name('purchases.payments.pdf');
        Route::resource('expenses', ExpenseController::class);
        Route::get('expenses/{expense}/print', [ExpenseController::class, 'print'])->name('expenses.print');
        Route::get('expenses/{expense}/pdf', [ExpenseController::class, 'downloadPdf'])->name('expenses.pdf');
        Route::resource('expenses-with-invoice', ExpenseWithInvoiceController::class)->parameters(['expenses-with-invoice' => 'expenseWithInvoice']);
        Route::resource('expenses-without-invoice', ExpenseWithoutInvoiceController::class)->parameters(['expenses-without-invoice' => 'expenseWithoutInvoice']);
        Route::resource('supplier-purchase-orders', SupplierPurchaseOrderController::class);
        Route::get('supplier-purchase-orders/{supplierPurchaseOrder}/print', [SupplierPurchaseOrderController::class, 'print'])->name('supplier-purchase-orders.print');
        Route::get('supplier-purchase-orders/{supplierPurchaseOrder}/pdf', [SupplierPurchaseOrderController::class, 'downloadPdf'])->name('supplier-purchase-orders.pdf');
        Route::get('supplier-delivery-notes/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'supplier-delivery-notes')->name('supplier-delivery-notes.import.template');
        Route::post('supplier-delivery-notes/import', [DocumentImportController::class, 'import'])->defaults('type', 'supplier-delivery-notes')->name('supplier-delivery-notes.import');
        Route::get('supplier-delivery-notes/{supplierDeliveryNote}/print', [SupplierDeliveryNoteController::class, 'print'])->name('supplier-delivery-notes.print');
        Route::get('supplier-delivery-notes/{supplierDeliveryNote}/pdf', [SupplierDeliveryNoteController::class, 'downloadPdf'])->name('supplier-delivery-notes.pdf');
        Route::post('supplier-delivery-notes/bulk-convert', [SupplierDeliveryNoteController::class, 'bulkConvert'])->name('supplier-delivery-notes.bulk-convert');
        Route::resource('supplier-delivery-notes', SupplierDeliveryNoteController::class);
        Route::post('receptions/bulk-convert', [ReceptionController::class, 'bulkConvert'])->name('receptions.bulk-convert');
        Route::get('receptions/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'receptions')->name('receptions.import.template');
        Route::post('receptions/import', [DocumentImportController::class, 'import'])->defaults('type', 'receptions')->name('receptions.import');
        Route::resource('receptions', ReceptionController::class);
        Route::get('receptions/{reception}/print', [ReceptionController::class, 'print'])->name('receptions.print');
        Route::get('receptions/{reception}/pdf', [ReceptionController::class, 'downloadPdf'])->name('receptions.pdf');
        Route::get('supplier-invoices/import/template', [DocumentImportController::class, 'downloadTemplate'])->defaults('type', 'supplier-invoices')->name('supplier-invoices.import.template');
        Route::post('supplier-invoices/import', [DocumentImportController::class, 'import'])->defaults('type', 'supplier-invoices')->name('supplier-invoices.import');
        Route::get('supplier-invoices/by-supplier/{supplier}', [SupplierInvoiceController::class, 'bySupplier'])->name('supplier-invoices.by-supplier');
        Route::resource('supplier-invoices', SupplierInvoiceController::class);
        Route::post('supplier-invoices/{supplierInvoice}/receive-stock', [SupplierInvoiceController::class, 'receiveStock'])->name('supplier-invoices.receive-stock');
        Route::get('supplier-invoices/{supplierInvoice}/print', [SupplierInvoiceController::class, 'print'])->name('supplier-invoices.print');
        Route::get('supplier-invoices/{supplierInvoice}/pdf', [SupplierInvoiceController::class, 'downloadPdf'])->name('supplier-invoices.pdf');
        Route::get('supplier-invoices/{supplierInvoice}/payments', [SupplierInvoicePaymentController::class, 'index'])->name('supplier-invoices.payments.index');
        Route::post('supplier-invoices/{supplierInvoice}/payments', [SupplierInvoicePaymentController::class, 'store'])->name('supplier-invoices.payments.store');
        Route::delete('supplier-invoices/{supplierInvoice}/payments/{payment}', [SupplierInvoicePaymentController::class, 'destroy'])->name('supplier-invoices.payments.destroy');
        Route::resource('supplier-credit-notes', SupplierCreditNoteController::class);
        Route::get('supplier-credit-notes/{supplierCreditNote}/print', [SupplierCreditNoteController::class, 'print'])->name('supplier-credit-notes.print');
        Route::get('supplier-credit-notes/{supplierCreditNote}/pdf', [SupplierCreditNoteController::class, 'downloadPdf'])->name('supplier-credit-notes.pdf');
    });

    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PointOfSaleController::class, 'index'])->name('index');
        Route::get('/products/search', [PointOfSaleController::class, 'searchProducts'])->name('products.search');
        Route::get('/products/catalog', [PointOfSaleController::class, 'catalog'])->name('products.catalog');
        Route::post('/checkout', [PointOfSaleController::class, 'checkout'])->name('checkout');
        Route::get('/sales', [PosSaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [PosSaleController::class, 'show'])->name('sales.show');
        Route::delete('/sales/{sale}', [PosSaleController::class, 'destroy'])->name('sales.destroy');
    });

    Route::prefix('hr')->name('hr.')->middleware('hr:view_hr')->group(function () {
        Route::get('/', HrDashboardController::class)->name('dashboard');
        Route::get('/employees/import/template', [HrImportController::class, 'template'])->name('employees.import.template');
        Route::post('/employees/import', [HrImportController::class, 'store'])->middleware('hr:edit_hr')->name('employees.import');
        Route::resource('employees', EmployeeController::class)->except(['destroy']);
        Route::post('/employees/{employee}/contracts', [EmployeeContractController::class, 'store'])->middleware('hr:edit_hr')->name('employees.contracts.store');
        Route::put('/employees/{employee}/contracts/{contract}', [EmployeeContractController::class, 'update'])->middleware('hr:edit_hr')->name('employees.contracts.update');
        Route::post('/employees/{employee}/salary', [CompensationController::class, 'storeSalary'])->middleware('hr:view_salaries')->name('employees.salary.store');
        Route::post('/employees/{employee}/leave-balance', [LeaveController::class, 'storeBalance'])->middleware('hr:edit_hr')->name('employees.leave-balance.store');
        Route::put('/employees/{employee}/schedule', [AttendanceController::class, 'updateSchedule'])->middleware('hr:edit_hr')->name('employees.schedule.update');
        Route::post('/employees/{employee}/exit', [EmployeeExitController::class, 'store'])->middleware('hr:edit_hr')->name('employees.exit');
        Route::get('/contracts', [EmployeeContractController::class, 'index'])->name('contracts.index');
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])->middleware('hr:edit_hr')->name('attendance.store');
        Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
        Route::post('/leaves', [LeaveController::class, 'store'])->middleware('hr:edit_hr')->name('leaves.store');
        Route::post('/leaves/{leave}/review', [LeaveController::class, 'review'])->middleware('hr:edit_hr')->name('leaves.review');
        Route::post('/absences', [LeaveController::class, 'storeAbsence'])->middleware('hr:edit_hr')->name('absences.store');
        Route::get('/compensations', [CompensationController::class, 'index'])->middleware('hr:view_salaries')->name('compensations.index');
        Route::post('/compensations', [CompensationController::class, 'store'])->middleware('hr:view_salaries')->name('compensations.store');
        Route::post('/adjustments', [CompensationController::class, 'storeAdjustment'])->middleware('hr:view_salaries')->name('adjustments.store');
        Route::get('/payroll', [PayrollController::class, 'index'])->middleware('hr:view_salaries')->name('payroll.index');
        Route::post('/payroll/prepare', [PayrollController::class, 'prepare'])->middleware('hr:prepare_payroll')->name('payroll.prepare');
        Route::get('/payroll/simulate', [PayrollController::class, 'simulate'])->middleware('hr:view_salaries')->name('payroll.simulate');
        Route::post('/payroll/slips/{slip}/pay', [PayrollController::class, 'pay'])->middleware('hr:pay_payroll')->name('payroll.pay');
        Route::get('/payroll/slips/{slip}/pdf', [PayrollController::class, 'pdf'])->middleware('hr:view_salaries')->name('payroll.slip.pdf');
        Route::get('/payroll/slips/{slip}/print', [PayrollController::class, 'print'])->middleware('hr:view_salaries')->name('payroll.slip.print');
        Route::get('/payroll/{payrollRun}', [PayrollController::class, 'show'])->middleware('hr:view_salaries')->name('payroll.show');
        Route::post('/payroll/{payrollRun}/calculate', [PayrollController::class, 'calculate'])->middleware('hr:prepare_payroll')->name('payroll.calculate');
        Route::post('/payroll/{payrollRun}/transition', [PayrollController::class, 'transition'])->middleware('hr:validate_payroll')->name('payroll.transition');
        Route::get('/documents', [HrDocumentController::class, 'index'])->middleware('hr:view_hr_documents')->name('documents.index');
        Route::get('/history', [HrHistoryController::class, 'index'])->name('history.index');
        Route::get('/reports', [HrReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [HrReportController::class, 'export'])->name('reports.export');
        Route::get('/settings', [HrSettingsController::class, 'index'])->middleware('hr:manage_hr_settings')->name('settings.index');
        Route::post('/settings/alerts', [HrSettingsController::class, 'updateAlerts'])->middleware('hr:manage_hr_settings')->name('settings.alerts');
        Route::post('/settings/options', [HrSettingsController::class, 'updateOptions'])->middleware('hr:manage_hr_settings')->name('settings.options');
        Route::post('/settings/permissions/{user}', [HrSettingsController::class, 'updatePermissions'])->middleware('hr:manage_hr_settings')->name('settings.permissions');
        Route::post('/settings/departments', [HrSettingsController::class, 'storeDepartment'])->middleware('hr:manage_hr_settings')->name('settings.departments.store');
        Route::post('/settings/leave-types', [HrSettingsController::class, 'storeLeaveType'])->middleware('hr:manage_hr_settings')->name('settings.leave-types.store');
        Route::post('/settings/rule-sets', [HrSettingsController::class, 'storeRuleSet'])->middleware('hr:manage_hr_settings')->name('settings.rule-sets.store');
    });

    Route::get('/integrations/shopify', [ShopifyIntegrationController::class, 'edit'])->name('integrations.shopify.edit');
    Route::put('/integrations/shopify', [ShopifyIntegrationController::class, 'update'])->name('integrations.shopify.update');
    Route::post('/integrations/shopify/sync', [ShopifyIntegrationController::class, 'sync'])->name('integrations.shopify.sync');
    Route::delete('/integrations/shopify', [ShopifyIntegrationController::class, 'destroy'])->name('integrations.shopify.destroy');
    Route::get('/integrations/shopify/install', [ShopifyIntegrationController::class, 'install'])->name('integrations.shopify.install');
    Route::get('/integrations/shopify/callback', [ShopifyIntegrationController::class, 'callback'])->name('integrations.shopify.callback');
    Route::get('/integrations/shopify/request-optional-scopes', [ShopifyIntegrationController::class, 'requestOptionalScopes'])->name('integrations.shopify.request-optional-scopes');
    Route::post('/integrations/shopify/refresh-scopes', [ShopifyIntegrationController::class, 'refreshScopes'])->name('integrations.shopify.refresh-scopes');

    Route::get('/integrations/jumia', [JumiaIntegrationController::class, 'edit'])->name('integrations.jumia.edit');
    Route::put('/integrations/jumia', [JumiaIntegrationController::class, 'update'])->name('integrations.jumia.update');
    Route::post('/integrations/jumia/test', [JumiaIntegrationController::class, 'test'])->name('integrations.jumia.test');
    Route::post('/integrations/jumia/sync', [JumiaIntegrationController::class, 'sync'])->name('integrations.jumia.sync');
    Route::post('/integrations/jumia/sync-stock', [JumiaIntegrationController::class, 'syncStock'])->name('integrations.jumia.sync-stock');
    Route::delete('/integrations/jumia', [JumiaIntegrationController::class, 'destroy'])->name('integrations.jumia.destroy');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/entreprise', [SettingsController::class, 'entreprise'])->name('settings.entreprise');
    Route::get('/settings/numerotation', [SettingsController::class, 'numerotation'])->name('settings.numerotation');
    Route::get('/settings/catalogue', [SettingsController::class, 'catalogue'])->name('settings.catalogue');
    Route::get('/settings/fiscalite', [SettingsController::class, 'fiscalite'])->name('settings.fiscalite');
    Route::get('/settings/depenses', [SettingsController::class, 'depenses'])->name('settings.depenses');
    Route::get('/settings/stock', [SettingsController::class, 'stock'])->name('settings.stock');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::post('/export/table-destroy', TableBulkDestroyController::class)->name('table.bulk-destroy');
    Route::post('/export/table', [TableExportController::class, 'export'])->name('table.export');
    Route::get('/export/table/{export}/status', [TableExportController::class, 'status'])->name('table.export.status');
    Route::get('/export/table/{export}/download', [TableExportController::class, 'download'])->name('table.export.download');
    Route::post('/export/table-zip', [TableExportController::class, 'exportZip'])->name('table.export.zip');

    Route::get('/api/table-columns/{tableKey}', [TableColumnPreferenceController::class, 'show'])->name('table-columns.show');
    Route::put('/api/table-columns/{tableKey}', [TableColumnPreferenceController::class, 'update'])->name('table-columns.update');
    Route::put('/api/table-columns/{tableKey}/defaults', [TableColumnPreferenceController::class, 'updateDefaults'])->name('table-columns.defaults');
    Route::post('/api/table-columns/{tableKey}/reset', [TableColumnPreferenceController::class, 'reset'])->name('table-columns.reset');
});
