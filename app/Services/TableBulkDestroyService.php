<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TableBulkDestroyService
{
    public function __construct(
        protected StockMovementService $stockMovement
    ) {}

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->registry());
    }

    public function supports(string $type): bool
    {
        return array_key_exists($type, $this->registry());
    }

    /**
     * @param  list<int|string>  $ids
     * @return array{deleted: int, blocked: list<array{id: int, label: string, reason: string}>, message: string}
     */
    public function destroyMany(string $type, array $ids): array
    {
        $config = $this->registry()[$type] ?? null;
        if ($config === null) {
            return [
                'deleted' => 0,
                'blocked' => [],
                'message' => 'Suppression groupée non disponible pour ce type.',
            ];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query()->whereIn('id', $ids);
        if (isset($config['query']) && is_callable($config['query'])) {
            ($config['query'])($query);
        }

        $records = $query->get()->keyBy('id');
        $deleted = 0;
        $blocked = [];

        foreach ($ids as $id) {
            $record = $records->get($id);
            if (! $record) {
                $blocked[] = [
                    'id' => $id,
                    'label' => '#'.$id,
                    'reason' => 'introuvable',
                ];

                continue;
            }

            $label = $this->label($record, $config['label'] ?? 'id');
            $reason = null;

            if (isset($config['block']) && is_callable($config['block'])) {
                $reason = ($config['block'])($record);
            }

            if (is_string($reason) && $reason !== '') {
                $blocked[] = [
                    'id' => (int) $record->getKey(),
                    'label' => $label,
                    'reason' => $reason,
                ];

                continue;
            }

            try {
                DB::transaction(function () use ($record, $config) {
                    if (isset($config['beforeDelete']) && is_callable($config['beforeDelete'])) {
                        ($config['beforeDelete'])($record);
                    }

                    $this->deleteStoredFiles($record, $config['files'] ?? []);
                    $record->delete();
                });
                $deleted++;
            } catch (\Throwable $e) {
                $blocked[] = [
                    'id' => (int) $record->getKey(),
                    'label' => $label,
                    'reason' => 'impossible à supprimer ('.$e->getMessage().')',
                ];
            }
        }

        $message = $this->buildMessage($deleted, $blocked);

        return compact('deleted', 'blocked', 'message');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function registry(): array
    {
        $deleteDocumentFile = ['document_file_path'];
        $deleteInvoiceFile = ['invoice_file_path'];
        $deleteReceiptFile = ['receipt_file_path'];

        return [
            'invoices' => [
                'model' => Invoice::class,
                'label' => 'invoice_number',
                'files' => $deleteDocumentFile,
                'block' => function (Invoice $invoice) {
                    if ($invoice->payments()->exists()) {
                        return 'déjà payée ou partiellement payée';
                    }
                    if ($invoice->isPaid()) {
                        return 'déjà payée';
                    }
                    if ($invoice->creditNotes()->exists()) {
                        return 'liée à un avoir';
                    }
                    if (Quote::query()->where('converted_invoice_id', $invoice->id)->exists()
                        || PurchaseOrder::query()->where('converted_invoice_id', $invoice->id)->exists()
                        || DeliveryNote::query()->where('converted_invoice_id', $invoice->id)->exists()) {
                        return 'issue d’une conversion (devis/BC/BL)';
                    }

                    return null;
                },
                'beforeDelete' => function (Invoice $invoice) {
                    $invoice->load('items');
                    $this->stockMovement->increaseFromItems($invoice->items, $invoice->stock_location);
                },
            ],
            'quotes' => [
                'model' => Quote::class,
                'label' => 'quote_number',
                'files' => $deleteDocumentFile,
                'block' => function (Quote $quote) {
                    if ($quote->isConvertedToPurchaseOrder()) {
                        return 'déjà converti en bon de commande';
                    }
                    if ($quote->isConvertedToDeliveryNote()) {
                        return 'déjà converti en bon de livraison';
                    }
                    if ($quote->isConvertedToInvoice()) {
                        return 'déjà converti en facture';
                    }

                    return null;
                },
            ],
            'purchase-orders' => [
                'model' => PurchaseOrder::class,
                'label' => 'reference',
                'files' => $deleteDocumentFile,
                'block' => function (PurchaseOrder $order) {
                    if ($order->isConvertedToDeliveryNote()) {
                        return 'déjà converti en bon de livraison';
                    }
                    if ($order->isConvertedToInvoice()) {
                        return 'déjà converti en facture';
                    }

                    return null;
                },
            ],
            'delivery-notes' => [
                'model' => DeliveryNote::class,
                'label' => 'delivery_number',
                'files' => $deleteDocumentFile,
                'block' => function (DeliveryNote $note) {
                    return $note->isConvertedToInvoice() ? 'déjà converti en facture' : null;
                },
            ],
            'credit-notes' => [
                'model' => CreditNote::class,
                'label' => 'credit_note_number',
                'files' => $deleteReceiptFile,
            ],
            'supplier-invoices' => [
                'model' => SupplierInvoice::class,
                'label' => 'invoice_number',
                'files' => $deleteInvoiceFile,
                'block' => function (SupplierInvoice $invoice) {
                    if ($invoice->payments()->exists()) {
                        return 'déjà payée ou partiellement payée';
                    }
                    if ($invoice->creditNotes()->exists()) {
                        return 'liée à un avoir';
                    }
                    if (Reception::query()->where('converted_supplier_invoice_id', $invoice->id)->exists()
                        || SupplierDeliveryNote::query()->where('converted_supplier_invoice_id', $invoice->id)->exists()) {
                        return 'issue d’une conversion (BL/BR)';
                    }

                    return null;
                },
            ],
            'supplier-delivery-notes' => [
                'model' => SupplierDeliveryNote::class,
                'label' => 'delivery_number',
                'files' => $deleteDocumentFile,
                'block' => function (SupplierDeliveryNote $note) {
                    if ($note->isConverted()) {
                        return 'déjà converti en facture fournisseur';
                    }
                    if (Reception::query()->where('converted_supplier_delivery_note_id', $note->id)->exists()) {
                        return 'issue d’une conversion (BR)';
                    }

                    return null;
                },
            ],
            'receptions' => [
                'model' => Reception::class,
                'label' => 'reception_number',
                'files' => $deleteDocumentFile,
                'block' => function (Reception $reception) {
                    if ($reception->isConverted()) {
                        return 'déjà converti en facture fournisseur';
                    }
                    if ($reception->isConvertedToDeliveryNote()) {
                        return 'déjà converti en bon de livraison';
                    }

                    return null;
                },
            ],
            'supplier-purchase-orders' => [
                'model' => SupplierPurchaseOrder::class,
                'label' => 'order_number',
            ],
            'supplier-credit-notes' => [
                'model' => SupplierCreditNote::class,
                'label' => 'credit_note_number',
                'files' => $deleteReceiptFile,
            ],
            'expenses' => [
                'model' => Expense::class,
                'label' => 'designation',
                'files' => $deleteInvoiceFile,
            ],
            'expenses-with-invoice' => [
                'model' => Expense::class,
                'query' => fn ($q) => $q->where('expense_type', 'with_invoice'),
                'label' => 'designation',
                'files' => $deleteInvoiceFile,
            ],
            'expenses-without-invoice' => [
                'model' => Expense::class,
                'query' => fn ($q) => $q->where('expense_type', 'without_invoice'),
                'label' => 'designation',
                'files' => $deleteInvoiceFile,
            ],
            'orders' => [
                'model' => PosSale::class,
                'label' => 'ticket_number',
                'block' => function (PosSale $order) {
                    if ($order->invoice()->exists()) {
                        return 'déjà convertie en facture';
                    }

                    return null;
                },
            ],
            'pos-sales' => [
                'model' => PosSale::class,
                'label' => 'ticket_number',
                'block' => function (PosSale $sale) {
                    if ($sale->invoice()->exists()) {
                        return 'déjà convertie en facture';
                    }

                    return null;
                },
            ],
            'products' => [
                'model' => Product::class,
                'label' => 'name',
                'files' => ['image'],
                'block' => function (Product $product) {
                    if ($product->invoiceItems()->exists()) {
                        return 'utilisé dans des ventes ou factures';
                    }

                    return null;
                },
            ],
            'clients' => [
                'model' => Client::class,
                'label' => 'name',
                'block' => function (Client $client) {
                    if ($client->invoices()->exists()
                        || $client->quotes()->exists()
                        || $client->purchaseOrders()->exists()
                        || $client->creditNotes()->exists()
                        || $client->posSales()->exists()
                        || DeliveryNote::query()->where('client_id', $client->id)->exists()) {
                        return 'utilisé dans des documents';
                    }

                    return null;
                },
            ],
            'suppliers' => [
                'model' => Supplier::class,
                'label' => 'name',
                'block' => function (Supplier $supplier) {
                    if ($supplier->invoices()->exists()
                        || $supplier->purchaseOrders()->exists()
                        || $supplier->creditNotes()->exists()
                        || $supplier->receptions()->exists()
                        || SupplierDeliveryNote::query()->where('supplier_id', $supplier->id)->exists()
                        || Expense::query()->where('supplier_id', $supplier->id)->exists()) {
                        return 'utilisé dans des documents';
                    }

                    return null;
                },
            ],
        ];
    }

    protected function label(Model $record, string $attribute): string
    {
        $value = $record->getAttribute($attribute);

        return is_string($value) && $value !== '' ? $value : '#'.$record->getKey();
    }

    /**
     * @param  list<string>  $fields
     */
    protected function deleteStoredFiles(Model $record, array $fields): void
    {
        foreach ($fields as $field) {
            $path = $record->getAttribute($field);
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * @param  list<array{id: int, label: string, reason: string}>  $blocked
     */
    protected function buildMessage(int $deleted, array $blocked): string
    {
        $parts = [];

        if ($deleted > 0) {
            $parts[] = $deleted === 1
                ? '1 élément a été supprimé.'
                : $deleted.' éléments ont été supprimés.';
        }

        if ($blocked !== []) {
            $parts[] = count($blocked) === 1
                ? '1 élément n’a pas pu être supprimé.'
                : count($blocked).' éléments n’ont pas pu être supprimés.';
        }

        if ($parts === []) {
            return 'Aucun élément n’a été supprimé.';
        }

        return implode(' ', $parts);
    }
}
