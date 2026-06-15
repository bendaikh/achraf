<?php

namespace App\Services;

use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\SupplierInvoice;
use App\Support\CommercialDocumentView;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'supplier-invoices',
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

    protected function loadRecords(string $type, array $ids)
    {
        return match ($type) {
            'invoices' => Invoice::with('client', 'items')->whereIn('id', $ids)->get(),
            'quotes' => Quote::with('client', 'items')->whereIn('id', $ids)->get(),
            'purchase-orders' => PurchaseOrder::with('client', 'items')->whereIn('id', $ids)->get(),
            'credit-notes' => CreditNote::with('client', 'invoice', 'items')->whereIn('id', $ids)->get(),
            'supplier-invoices' => SupplierInvoice::with('supplier', 'items')->whereIn('id', $ids)->get(),
            default => collect(),
        };
    }

    protected function renderPdfContent(string $type, $record): string
    {
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
            'supplier-invoices' => array_merge(
                CommercialDocumentView::forSupplierInvoice($record, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            default => [],
        };

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
            'supplier-invoices' => $record->invoice_number,
            default => (string) $record->id,
        };

        $prefix = match ($type) {
            'invoices' => 'facture',
            'quotes' => 'devis',
            'purchase-orders' => 'bc',
            'credit-notes' => 'avoir',
            'supplier-invoices' => 'facture-fournisseur',
            default => 'document',
        };

        return $prefix.'-'.Str::slug($number).'.pdf';
    }
}
