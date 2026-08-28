<?php

namespace App\Support;

use App\Models\DeliveryNote;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Reception;
use App\Models\SupplierCreditNote;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use App\Models\SupplierPayment;
use App\Models\SupplierPurchaseOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class DocumentAttachmentRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'expenses-with-invoice' => [
                'label' => 'Dépenses avec facture',
                'model' => Expense::class,
                'scope' => fn (Builder $query) => $query->where('expense_type', 'with_invoice'),
                'reference' => fn (Expense $model) => self::fallbackReference($model->reference, 'DEP', $model->id),
                'document_date' => fn (Expense $model) => $model->expense_date,
                'legacy_field' => 'invoice_file_path',
                'folder' => 'managed-documents/expenses-with-invoice',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => fn (Expense $model) => route('expenses-with-invoice.show', $model),
                'categories' => ['primary' => 'Facture / justificatif'],
            ],
            'expenses-without-invoice' => [
                'label' => 'Dépenses sans facture',
                'model' => Expense::class,
                'scope' => fn (Builder $query) => $query->where('expense_type', 'without_invoice'),
                'reference' => fn (Expense $model) => self::fallbackReference($model->reference, 'DSN', $model->id),
                'document_date' => fn (Expense $model) => $model->expense_date,
                'legacy_field' => 'invoice_file_path',
                'folder' => 'managed-documents/expenses-without-invoice',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => fn (Expense $model) => route('expenses-without-invoice.show', $model),
                'categories' => ['primary' => 'Justificatif'],
            ],
            'supplier-purchase-orders' => [
                'label' => 'BC fournisseur',
                'model' => SupplierPurchaseOrder::class,
                'reference' => fn (SupplierPurchaseOrder $model) => self::fallbackReference($model->order_number, 'BCF', $model->id),
                'document_date' => fn (SupplierPurchaseOrder $model) => $model->order_date,
                'legacy_field' => null,
                'folder' => 'managed-documents/supplier-purchase-orders',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => fn (SupplierPurchaseOrder $model) => route('supplier-purchase-orders.show', $model),
                'categories' => ['primary' => 'Bon de commande'],
            ],
            'supplier-delivery-notes' => [
                'label' => 'Bons de livraison fournisseur',
                'model' => SupplierDeliveryNote::class,
                'reference' => fn (SupplierDeliveryNote $model) => self::fallbackReference($model->delivery_number, 'BL', $model->id),
                'document_date' => fn (SupplierDeliveryNote $model) => $model->delivery_date,
                'legacy_field' => 'document_file_path',
                'folder' => 'managed-documents/supplier-delivery-notes',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => fn (SupplierDeliveryNote $model) => route('supplier-delivery-notes.show', $model),
                'categories' => ['primary' => 'Bon de livraison'],
            ],
            'receptions' => [
                'label' => 'Bons de réception',
                'model' => Reception::class,
                'reference' => fn (Reception $model) => self::fallbackReference($model->reception_number, 'BR', $model->id),
                'document_date' => fn (Reception $model) => $model->reception_date,
                'legacy_field' => 'document_file_path',
                'folder' => 'managed-documents/receptions',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => fn (Reception $model) => route('receptions.show', $model),
                'categories' => ['primary' => 'Bon de réception'],
            ],
            'supplier-invoices' => [
                'label' => 'Factures fournisseurs',
                'model' => SupplierInvoice::class,
                'reference' => fn (SupplierInvoice $model) => self::fallbackReference($model->invoice_number, 'FSI', $model->id),
                'document_date' => fn (SupplierInvoice $model) => $model->invoice_date,
                'legacy_field' => 'invoice_file_path',
                'folder' => 'managed-documents/supplier-invoices',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => fn (SupplierInvoice $model) => route('supplier-invoices.show', $model),
                'categories' => ['primary' => 'Facture fournisseur'],
            ],
            'supplier-credit-notes' => [
                'label' => 'Avoirs fournisseurs',
                'model' => SupplierCreditNote::class,
                'reference' => fn (SupplierCreditNote $model) => self::fallbackReference($model->credit_note_number, 'AVF', $model->id),
                'document_date' => fn (SupplierCreditNote $model) => $model->credit_note_date,
                'legacy_field' => 'receipt_file_path',
                'folder' => 'managed-documents/supplier-credit-notes',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => fn (SupplierCreditNote $model) => route('supplier-credit-notes.show', $model),
                'categories' => ['primary' => 'Avoir fournisseur'],
            ],
            'supplier-payment-headers' => [
                'label' => 'Justificatifs de règlement fournisseur',
                'model' => SupplierPayment::class,
                'reference' => fn (SupplierPayment $model) => self::fallbackReference($model->payment_number ?: $model->payment_reference, 'REG', $model->id),
                'document_date' => fn (SupplierPayment $model) => $model->payment_date,
                'legacy_field' => 'payment_file_path',
                'folder' => 'managed-documents/supplier-payments',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => fn (SupplierPayment $model) => route('purchases.payments.show', $model),
                'categories' => [
                    'primary' => 'Justificatif de paiement',
                    'transfer_proof' => 'Justificatif de virement',
                    'cheque_scan' => 'Scan du chèque',
                ],
            ],
            'supplier-payments' => [
                'label' => 'Justificatifs de paiement',
                'model' => SupplierInvoicePayment::class,
                'reference' => function (SupplierInvoicePayment $model) {
                    if ($model->payment_method === 'Chèque' && $model->cheque_number) {
                        return 'CHQ-'.$model->cheque_number;
                    }

                    return self::fallbackReference(
                        $model->payment_reference ?: optional($model->supplierInvoice)->invoice_number,
                        'PAY',
                        $model->id
                    );
                },
                'document_date' => fn (SupplierInvoicePayment $model) => $model->payment_date,
                'legacy_field' => 'payment_file_path',
                'folder' => 'managed-documents/supplier-payments',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => function (SupplierInvoicePayment $model) {
                    if ($model->supplier_payment_id) {
                        return route('purchases.payments.show', $model->supplier_payment_id);
                    }
                    if ($model->supplier_invoice_id) {
                        return route('supplier-invoices.payments.index', $model->supplier_invoice_id);
                    }

                    return $model->supplier_id
                        ? route('purchases.payments.settle', $model->supplier_id)
                        : route('purchases.payments.index');
                },
                'categories' => [
                    'primary' => 'Justificatif de paiement',
                    'transfer_proof' => 'Justificatif de virement',
                    'cheque_scan' => 'Scan du chèque',
                ],
            ],
            'hr-employees' => [
                'label' => 'Documents RH',
                'model' => Employee::class,
                'reference' => fn (Employee $model) => $model->matricule,
                'document_date' => fn (Employee $model) => $model->hire_date,
                'legacy_field' => null,
                'folder' => 'managed-documents/hr-employees',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => fn (Employee $model) => route('hr.employees.show', [$model, 'tab' => 'documents']),
                'categories' => [
                    'contrat' => 'Contrat de travail',
                    'cin' => 'CIN',
                    'administratif' => 'Document administratif',
                    'rib' => 'RIB',
                    'cnss' => 'Document CNSS',
                    'certificat_medical' => 'Certificat médical',
                    'permis' => 'Permis',
                    'attestation' => 'Attestation',
                    'absence' => 'Justificatif d\'absence',
                    'conge' => 'Document de congé',
                    'paie' => 'Document de paie',
                    'sortie' => 'Document de sortie',
                    'autre' => 'Autre document RH',
                ],
            ],
            'delivery-notes' => [
                'label' => 'Bons de livraison clients (signés)',
                'model' => DeliveryNote::class,
                'reference' => fn (DeliveryNote $model) => self::fallbackReference($model->delivery_number, 'BL', $model->id),
                'document_date' => fn (DeliveryNote $model) => $model->delivery_date,
                'legacy_field' => 'document_file_path',
                'folder' => 'managed-documents/delivery-notes-signed',
                'exportable' => true,
                'allow_attach' => true,
                'record_url' => fn (DeliveryNote $model) => route('delivery-notes.show', $model),
                'categories' => [
                    'signed' => 'BL signé / cacheté',
                    'primary' => 'Pièce jointe BL',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $sectionKey): array
    {
        $config = self::all()[$sectionKey] ?? null;

        if (! $config) {
            throw new InvalidArgumentException("Section documentaire inconnue: {$sectionKey}");
        }

        return $config;
    }

    public static function exists(string $sectionKey): bool
    {
        return array_key_exists($sectionKey, self::all());
    }

    /**
     * @return list<string>
     */
    public static function exportableKeys(): array
    {
        return array_keys(array_filter(
            self::all(),
            fn (array $config) => ($config['exportable'] ?? false) === true
        ));
    }

    public static function resolveRecord(string $sectionKey, int $id): Model
    {
        $config = self::get($sectionKey);
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query();

        if (isset($config['scope']) && is_callable($config['scope'])) {
            ($config['scope'])($query);
        }

        return $query->findOrFail($id);
    }

    public static function referenceFor(string $sectionKey, Model $record): string
    {
        $config = self::get($sectionKey);

        return (string) ($config['reference'])($record);
    }

    public static function documentDateFor(string $sectionKey, Model $record): ?CarbonInterface
    {
        $config = self::get($sectionKey);
        $date = ($config['document_date'])($record);

        return $date instanceof CarbonInterface ? $date : null;
    }

    public static function recordUrlFor(string $sectionKey, Model $record): ?string
    {
        $config = self::get($sectionKey);
        $resolver = $config['record_url'] ?? null;

        if (! is_callable($resolver)) {
            return null;
        }

        try {
            return (string) $resolver($record);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function typeLabelFor(string $sectionKey, string $category = 'primary'): string
    {
        $config = self::get($sectionKey);

        return $config['categories'][$category]
            ?? $config['label']
            ?? $sectionKey;
    }

    protected static function fallbackReference(?string $value, string $prefix, int $id): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : sprintf('%s-%d', $prefix, $id);
    }
}
