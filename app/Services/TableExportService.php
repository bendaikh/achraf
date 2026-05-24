<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TableExportService
{
    protected function registry(): array
    {
        return [
            'products' => [
                'model' => \App\Models\Product::class,
                'columns' => [
                    'ref' => 'Référence',
                    'name' => 'Nom',
                    'sale_price' => 'Prix de vente',
                    'stock_quantity' => 'Stock',
                    'minimum_alert_stock' => 'Stock alerte',
                    'status' => 'Statut',
                    'source' => 'Source',
                ],
            ],
            'clients' => [
                'model' => \App\Models\Client::class,
                'columns' => [
                    'name' => 'Nom',
                    'email' => 'Email',
                    'phone' => 'Téléphone',
                    'city' => 'Ville',
                    'country' => 'Pays',
                ],
            ],
            'suppliers' => [
                'model' => \App\Models\Supplier::class,
                'columns' => [
                    'name' => 'Nom',
                    'email' => 'Email',
                    'phone' => 'Téléphone',
                    'city' => 'Ville',
                    'country' => 'Pays',
                ],
            ],
            'invoices' => [
                'model' => \App\Models\Invoice::class,
                'query' => fn (Builder $q) => $q->with('client'),
                'columns' => [
                    'invoice_number' => 'Numéro',
                    'client.name' => 'Client',
                    'invoice_date' => 'Date',
                    'total' => 'Total',
                    'currency' => 'Devise',
                ],
            ],
            'quotes' => [
                'model' => \App\Models\Quote::class,
                'query' => fn (Builder $q) => $q->with('client'),
                'columns' => [
                    'quote_number' => 'Numéro',
                    'client.name' => 'Client',
                    'quote_date' => 'Date',
                    'total' => 'Total',
                    'currency' => 'Devise',
                ],
            ],
            'purchase-orders' => [
                'model' => \App\Models\PurchaseOrder::class,
                'query' => fn (Builder $q) => $q->with('client'),
                'columns' => [
                    'reference' => 'Numéro',
                    'client.name' => 'Client',
                    'order_date' => 'Date',
                    'total' => 'Total',
                ],
            ],
            'credit-notes' => [
                'model' => \App\Models\CreditNote::class,
                'query' => fn (Builder $q) => $q->with('client'),
                'columns' => [
                    'credit_note_number' => 'Numéro',
                    'client.name' => 'Client',
                    'credit_note_date' => 'Date',
                    'total' => 'Total',
                ],
            ],
            'orders' => [
                'model' => \App\Models\PosSale::class,
                'query' => fn (Builder $q) => $q->with('client'),
                'columns' => [
                    'ticket_number' => 'N° Commande',
                    'source' => 'Source',
                    'client.name' => 'Client',
                    'sold_at' => 'Date',
                    'total' => 'Total',
                ],
            ],
            'expenses-with-invoice' => [
                'model' => \App\Models\Expense::class,
                'query' => fn (Builder $q) => $q->with('supplier')->where('expense_type', 'with_invoice'),
                'columns' => [
                    'designation' => 'Désignation',
                    'expense_category' => 'Catégorie',
                    'expense_date' => 'Date',
                    'amount' => 'Montant',
                    'supplier.name' => 'Fournisseur',
                ],
            ],
            'expenses-without-invoice' => [
                'model' => \App\Models\Expense::class,
                'query' => fn (Builder $q) => $q->with('client')->where('expense_type', 'without_invoice'),
                'columns' => [
                    'designation' => 'Désignation',
                    'expense_category' => 'Catégorie',
                    'expense_date' => 'Date',
                    'amount' => 'Montant',
                    'client.name' => 'Client',
                ],
            ],
            'expenses' => [
                'model' => \App\Models\Expense::class,
                'query' => fn (Builder $q) => $q->with(['client', 'supplier']),
                'columns' => [
                    'designation' => 'Désignation',
                    'expense_type' => 'Type',
                    'expense_category' => 'Catégorie',
                    'expense_date' => 'Date',
                    'amount' => 'Montant',
                ],
            ],
            'supplier-invoices' => [
                'model' => \App\Models\SupplierInvoice::class,
                'query' => fn (Builder $q) => $q->with('supplier'),
                'columns' => [
                    'invoice_number' => 'Numéro',
                    'supplier.name' => 'Fournisseur',
                    'invoice_date' => 'Date',
                    'total' => 'Total',
                ],
            ],
            'supplier-purchase-orders' => [
                'model' => \App\Models\SupplierPurchaseOrder::class,
                'query' => fn (Builder $q) => $q->with('supplier'),
                'columns' => [
                    'order_number' => 'Numéro',
                    'supplier.name' => 'Fournisseur',
                    'order_date' => 'Date',
                    'total' => 'Total',
                ],
            ],
            'supplier-credit-notes' => [
                'model' => \App\Models\SupplierCreditNote::class,
                'query' => fn (Builder $q) => $q->with('supplier'),
                'columns' => [
                    'credit_note_number' => 'Numéro',
                    'supplier.name' => 'Fournisseur',
                    'credit_note_date' => 'Date',
                    'total' => 'Total',
                ],
            ],
            'receptions' => [
                'model' => \App\Models\Reception::class,
                'query' => fn (Builder $q) => $q->with('supplier'),
                'columns' => [
                    'reception_number' => 'Numéro',
                    'supplier.name' => 'Fournisseur',
                    'reception_date' => 'Date',
                ],
            ],
            'pos-sales' => [
                'model' => \App\Models\PosSale::class,
                'query' => fn (Builder $q) => $q->with('client'),
                'columns' => [
                    'ticket_number' => 'Ticket',
                    'sold_at' => 'Date',
                    'total' => 'Total',
                    'payment_method' => 'Paiement',
                ],
            ],
            'stock-enligne' => [
                'model' => \App\Models\Product::class,
                'columns' => [
                    'ref' => 'Référence',
                    'name' => 'Nom',
                    'stock_enligne' => 'Stock en ligne',
                    'minimum_alert_stock' => 'Stock alerte',
                ],
            ],
            'stock-magasin' => [
                'model' => \App\Models\Product::class,
                'columns' => [
                    'ref' => 'Référence',
                    'name' => 'Nom',
                    'stock_magasin' => 'Stock magasin',
                    'minimum_alert_stock' => 'Stock alerte',
                ],
            ],
        ];
    }

    public function types(): array
    {
        return array_keys($this->registry());
    }

    public function export(string $type, array $ids): StreamedResponse
    {
        $registry = $this->registry();

        if (! isset($registry[$type])) {
            abort(422, 'Type d\'export inconnu.');
        }

        $config = $registry[$type];
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $query = $modelClass::query()->whereIn('id', $ids);
        if (isset($config['query']) && is_callable($config['query'])) {
            ($config['query'])($query);
        }

        $rows = $query->get();
        $columns = $config['columns'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $col = 1;
        foreach ($columns as $label) {
            $sheet->setCellValue([$col, 1], $label);
            $col++;
        }

        $rowNum = 2;
        foreach ($rows as $row) {
            $col = 1;
            foreach (array_keys($columns) as $field) {
                $sheet->setCellValue([$col, $rowNum], $this->resolveValue($row, $field));
                $col++;
            }
            $rowNum++;
        }

        $filename = $type . '-export-' . now()->format('Y-m-d-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function resolveValue(Model $model, string $field): mixed
    {
        if (! str_contains($field, '.')) {
            $value = $model->getAttribute($field);
            if ($value instanceof \DateTimeInterface) {
                return $value->format('d/m/Y');
            }

            return $value;
        }

        $parts = explode('.', $field);
        $current = $model;
        foreach ($parts as $part) {
            if ($current === null) {
                return '';
            }
            $current = $current->{$part} ?? null;
        }

        if ($current instanceof \DateTimeInterface) {
            return $current->format('d/m/Y');
        }

        return $current ?? '';
    }
}
