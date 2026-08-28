<?php

namespace App\Services;

use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Reception;
use App\Models\SupplierCreditNote;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Support\CommercialDocumentView;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BulkCommercialPdfExportService
{
    use PreparesPrintView;

    public function supportsZip(string $type): bool
    {
        return in_array($type, $this->supportedTypes(), true);
    }

    /**
     * @return list<string>
     */
    public function supportedTypes(): array
    {
        return [
            'invoices',
            'quotes',
            'purchase-orders',
            'credit-notes',
            'delivery-notes',
            'supplier-invoices',
            'supplier-delivery-notes',
            'receptions',
            'supplier-purchase-orders',
            'supplier-credit-notes',
            'expenses',
            'expenses-with-invoice',
            'expenses-without-invoice',
        ];
    }

    public function exportZip(string $type, array $ids): StreamedResponse
    {
        if (! $this->supportsZip($type)) {
            abort(422, 'Export ZIP PDF non disponible pour ce type.');
        }

        $records = $this->loadRecords($type, $ids);

        if ($records->isEmpty()) {
            abort(404, 'Aucun document trouvé.');
        }

        $zipFilename = $type.'-pdf-'.now()->format('Y-m-d-His').'.zip';

        return response()->streamDownload(function () use ($type, $records) {
            $zipPath = tempnam(sys_get_temp_dir(), 'pdfzip');
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Impossible de créer le fichier ZIP.');
            }

            foreach ($records as $record) {
                $pdfContent = $this->renderPdfContent($type, $record);
                $filename = $this->pdfFilename($type, $record);
                $zip->addFromString($filename, $pdfContent);
            }

            $zip->close();
            readfile($zipPath);
            @unlink($zipPath);
        }, $zipFilename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function exportZipToStorage(string $type, array $ids, ?callable $progress = null): array
    {
        if (! $this->supportsZip($type)) {
            throw new \RuntimeException('Export ZIP PDF non disponible pour ce type.');
        }

        $records = $this->loadRecords($type, $ids);

        if ($records->isEmpty()) {
            throw new \RuntimeException('Aucun document trouvé.');
        }

        $zipFilename = $type.'-pdf-'.now()->format('Y-m-d-His').'.zip';
        $path = 'exports/'.$zipFilename;

        Storage::disk('public')->makeDirectory('exports');

        $zipPath = tempnam(sys_get_temp_dir(), 'pdfzip');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le fichier ZIP.');
        }

        $total = $records->count();
        $processed = 0;

        foreach ($records as $record) {
            $pdfContent = $this->renderPdfContent($type, $record);
            $filename = $this->pdfFilename($type, $record);
            $zip->addFromString($filename, $pdfContent);

            $processed++;
            if ($progress) {
                $progress((int) floor(($processed / $total) * 90));
            }
        }

        $zip->close();

        Storage::disk('public')->put($path, file_get_contents($zipPath));
        @unlink($zipPath);

        return [
            'filename' => $zipFilename,
            'path' => $path,
        ];
    }

    protected function loadRecords(string $type, array $ids)
    {
        return match ($type) {
            'invoices' => Invoice::with('client', 'items')->whereIn('id', $ids)->get(),
            'quotes' => Quote::with('client', 'items')->whereIn('id', $ids)->get(),
            'purchase-orders' => PurchaseOrder::with('client', 'items')->whereIn('id', $ids)->get(),
            'credit-notes' => CreditNote::with('client', 'invoice', 'items')->whereIn('id', $ids)->get(),
            'delivery-notes' => DeliveryNote::with('client', 'items')->whereIn('id', $ids)->get(),
            'supplier-invoices' => SupplierInvoice::with('supplier', 'items')->whereIn('id', $ids)->get(),
            'supplier-delivery-notes' => SupplierDeliveryNote::with('supplier', 'items')->whereIn('id', $ids)->get(),
            'receptions' => Reception::with('supplier', 'items')->whereIn('id', $ids)->get(),
            'supplier-purchase-orders' => SupplierPurchaseOrder::with('supplier', 'items')->whereIn('id', $ids)->get(),
            'supplier-credit-notes' => SupplierCreditNote::with('supplier', 'supplierInvoice', 'items')->whereIn('id', $ids)->get(),
            'expenses', 'expenses-with-invoice', 'expenses-without-invoice' => Expense::with('supplier', 'client')->whereIn('id', $ids)->get(),
            default => collect(),
        };
    }

    protected function renderPdfContent(string $type, $record): string
    {
        if (in_array($type, ['expenses', 'expenses-with-invoice', 'expenses-without-invoice'], true)) {
            $preview = CommercialDocumentView::forExpense($record, []);
            $printData = $this->printViewData($record, $preview['doc']['items']);
            $viewData = array_merge(
                CommercialDocumentView::forExpense($record, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            );
        } else {
            $printData = $this->printViewData($record, $record->items);

            $viewData = match ($type) {
                'invoices' => array_merge(
                    CommercialDocumentView::forInvoice($record, $printData['taxes']),
                    $printData,
                    ['generatedBy' => auth()->user()?->name]
                ),
                'quotes' => array_merge(
                    CommercialDocumentView::forQuote($record, $printData['taxes']),
                    $printData,
                    ['generatedBy' => auth()->user()?->name]
                ),
                'purchase-orders' => array_merge(
                    CommercialDocumentView::forPurchaseOrder($record, $printData['taxes']),
                    $printData,
                    ['generatedBy' => auth()->user()?->name]
                ),
                'credit-notes' => array_merge(
                    CommercialDocumentView::forCreditNote($record, $printData['taxes']),
                    $printData,
                    ['generatedBy' => auth()->user()?->name]
                ),
                'delivery-notes' => array_merge(
                    CommercialDocumentView::forDeliveryNote($record, $printData['taxes']),
                    $printData,
                    ['generatedBy' => auth()->user()?->name]
                ),
                'supplier-invoices' => array_merge(
                    CommercialDocumentView::forSupplierInvoice($record, $printData['taxes']),
                    $printData,
                    ['generatedBy' => auth()->user()?->name]
                ),
                'supplier-delivery-notes' => array_merge(
                    CommercialDocumentView::forSupplierDeliveryNote($record, $printData['taxes']),
                    $printData,
                    ['generatedBy' => auth()->user()?->name]
                ),
                'receptions' => array_merge(
                    CommercialDocumentView::forReception($record, $printData['taxes']),
                    $printData,
                    ['generatedBy' => auth()->user()?->name]
                ),
                'supplier-purchase-orders' => array_merge(
                    CommercialDocumentView::forSupplierPurchaseOrder($record, $printData['taxes']),
                    $printData,
                    ['generatedBy' => auth()->user()?->name]
                ),
                'supplier-credit-notes' => array_merge(
                    CommercialDocumentView::forSupplierCreditNote($record, $printData['taxes']),
                    $printData,
                    ['generatedBy' => auth()->user()?->name]
                ),
                default => [],
            };
        }

        $pdf = Pdf::loadView('documents.pdf', $viewData);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    protected function pdfFilename(string $type, $record): string
    {
        $number = match ($type) {
            'invoices' => $record->invoice_number,
            'quotes' => $record->quote_number,
            'purchase-orders' => $record->reference,
            'credit-notes' => $record->credit_note_number,
            'delivery-notes' => $record->delivery_number,
            'supplier-invoices' => $record->invoice_number,
            'supplier-delivery-notes' => $record->delivery_number,
            'receptions' => $record->reception_number,
            'supplier-purchase-orders' => $record->order_number,
            'supplier-credit-notes' => $record->credit_note_number,
            'expenses', 'expenses-with-invoice', 'expenses-without-invoice' => $record->reference ?: 'DEP-'.$record->id,
            default => (string) $record->id,
        };

        $prefix = match ($type) {
            'invoices' => 'facture',
            'quotes' => 'devis',
            'purchase-orders' => 'bc',
            'credit-notes' => 'avoir',
            'delivery-notes' => 'bl',
            'supplier-invoices' => 'facture-fournisseur',
            'supplier-delivery-notes' => 'bl-fournisseur',
            'receptions' => 'bon-reception',
            'supplier-purchase-orders' => 'bc-fournisseur',
            'supplier-credit-notes' => 'avoir-fournisseur',
            'expenses', 'expenses-with-invoice', 'expenses-without-invoice' => 'depense',
            default => 'document',
        };

        return $prefix.'-'.Str::slug($number).'.pdf';
    }
}
