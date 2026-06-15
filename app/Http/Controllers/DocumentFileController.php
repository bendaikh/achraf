<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Reception;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentFileController extends Controller
{
    /**
     * @return array<string, array{model: class-string<Model>, field: string, folder: string, redirect_route: string}>
     */
    protected function registry(): array
    {
        return [
            'credit-notes' => [
                'model' => CreditNote::class,
                'field' => 'receipt_file_path',
                'folder' => 'documents/credit-notes/receipts',
                'redirect_route' => 'credit-notes.index',
            ],
            'supplier-credit-notes' => [
                'model' => SupplierCreditNote::class,
                'field' => 'receipt_file_path',
                'folder' => 'documents/supplier-credit-notes/receipts',
                'redirect_route' => 'supplier-credit-notes.index',
            ],
            'invoices' => [
                'model' => Invoice::class,
                'field' => 'document_file_path',
                'folder' => 'documents/invoices',
                'redirect_route' => 'invoices.index',
            ],
            'quotes' => [
                'model' => Quote::class,
                'field' => 'document_file_path',
                'folder' => 'documents/quotes',
                'redirect_route' => 'quotes.index',
            ],
            'purchase-orders' => [
                'model' => PurchaseOrder::class,
                'field' => 'document_file_path',
                'folder' => 'documents/purchase-orders',
                'redirect_route' => 'purchase-orders.index',
            ],
            'supplier-invoices' => [
                'model' => SupplierInvoice::class,
                'field' => 'invoice_file_path',
                'folder' => 'supplier_invoices',
                'redirect_route' => 'supplier-invoices.index',
            ],
            'receptions' => [
                'model' => Reception::class,
                'field' => 'document_file_path',
                'folder' => 'documents/receptions',
                'redirect_route' => 'receptions.index',
            ],
            'expenses-with-invoice' => [
                'model' => Expense::class,
                'field' => 'invoice_file_path',
                'folder' => 'expenses/invoices',
                'redirect_route' => 'expenses-with-invoice.index',
            ],
        ];
    }

    public function store(Request $request, string $type, int $id)
    {
        $config = $this->registry()[$type] ?? null;

        if (! $config) {
            abort(404);
        }

        $request->validate([
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $record = $modelClass::query()->findOrFail($id);
        $field = $config['field'];

        $existing = $record->getAttribute($field);
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        $path = $request->file('document_file')->store($config['folder'], 'public');
        $record->update([$field => $path]);

        return redirect()
            ->route($config['redirect_route'])
            ->with('success', 'Document importé avec succès.');
    }
}
