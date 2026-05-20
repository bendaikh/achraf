<?php

namespace App\Services\BulkImport;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\DocumentNumberService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkDocumentImportService
{
    public function downloadTemplate(string $type): StreamedResponse
    {
        $config = DocumentImportRegistry::get($type);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import');

        $headers = array_keys($config['columns']);
        foreach ($headers as $index => $header) {
            $column = $this->columnLetter($index + 1);
            $sheet->setCellValue("{$column}1", $header);
            $sheet->getStyle("{$column}1")->getFont()->setBold(true);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $example = $config['example'];
        foreach ($headers as $index => $header) {
            $column = $this->columnLetter($index + 1);
            $sheet->setCellValue("{$column}2", $example[$header] ?? '');
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->setCellValue('A1', 'Instructions d\'import');
        $instructions->getStyle('A1')->getFont()->setBold(true);
        $instructions->setCellValue('A3', '1. Utilisez reference_import pour regrouper plusieurs lignes dans un même document.');
        $instructions->setCellValue('A4', '2. Chaque ligne représente un article. Répétez les informations d\'en-tête sur chaque ligne du même document.');
        $instructions->setCellValue('A5', '3. Les dates doivent être au format AAAA-MM-JJ (ex: 2026-01-15).');
        $instructions->setCellValue('A6', '4. client / fournisseur doit correspondre exactement au nom enregistré dans le CRM.');
        $instructions->setCellValue('A7', '5. Supprimez la ligne d\'exemple avant l\'import.');
        $instructions->getColumnDimension('A')->setWidth(80);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $config['template_filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(string $type, UploadedFile $file): array
    {
        $config = DocumentImportRegistry::get($type);
        $rows = $this->parseFile($file, $config);

        if (empty($rows)) {
            return [
                'created' => 0,
                'errors' => ['Le fichier ne contient aucune ligne de données.'],
            ];
        }

        $groups = $this->groupRows($rows);
        $errors = [];
        $created = 0;

        DB::beginTransaction();

        try {
            foreach ($groups as $reference => $groupRows) {
                try {
                    $this->createDocument($type, $config, $reference, $groupRows);
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = "Document {$reference}: {$e->getMessage()}";
                }
            }

            if ($created === 0) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return compact('created', 'errors');
    }

    private function parseFile(UploadedFile $file, array $config): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return [];
        }

        $headerRow = array_shift($rows);
        $headerMap = $this->buildHeaderMap($headerRow, $config);

        $parsed = [];
        $lineNumber = 1;

        foreach ($rows as $row) {
            $lineNumber++;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $record = [];
            foreach ($headerMap as $columnKey => $columnIndex) {
                $record[$columnKey] = $this->normalizeCellValue($row[$columnIndex] ?? null);
            }

            $parsed[] = [
                'line' => $lineNumber,
                'data' => $record,
            ];
        }

        return $parsed;
    }

    private function buildHeaderMap(array $headerRow, array $config): array
    {
        $normalizedHeaders = [];
        foreach ($headerRow as $index => $value) {
            $normalizedHeaders[$this->normalizeKey($value)] = $index;
        }

        $map = [];
        foreach (array_keys($config['columns']) as $columnKey) {
            if (! isset($normalizedHeaders[$columnKey])) {
                throw new \RuntimeException("Colonne manquante dans le fichier: {$columnKey}");
            }
            $map[$columnKey] = $normalizedHeaders[$columnKey];
        }

        return $map;
    }

    private function groupRows(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $reference = trim((string) ($row['data']['reference_import'] ?? ''));

            if ($reference === '') {
                throw new \RuntimeException("Ligne {$row['line']}: reference_import est obligatoire.");
            }

            $groups[$reference][] = $row;
        }

        return $groups;
    }

    private function createDocument(string $type, array $config, string $reference, array $groupRows): void
    {
        $firstRow = $groupRows[0]['data'];
        $headerData = $this->extractHeaderData($config, $firstRow, $groupRows[0]['line']);
        $partyId = $this->resolveParty($config, $firstRow, $groupRows[0]['line']);

        $items = [];
        foreach ($groupRows as $row) {
            $items[] = $this->extractItemData($config, $row['data'], $row['line']);
        }

        $modelClass = $config['model'];
        $numberField = $config['number_field'];
        $documentNumber = $this->resolveDocumentNumber($config, $headerData);

        $attributes = array_merge($headerData, [
            $numberField => $documentNumber,
            $config['party_type'] === 'client' ? 'client_id' : 'supplier_id' => $partyId,
            'subtotal' => 0,
            'discount' => 0,
            'adjustment' => (float) ($headerData['adjustment'] ?? 0),
            'total' => 0,
        ]);

        unset($attributes['adjustment'], $attributes['invoice_number']);

        if ($type === 'credit-notes' && ! empty($headerData['invoice_number'])) {
            $invoice = Invoice::where('invoice_number', $headerData['invoice_number'])->first();
            if (! $invoice) {
                throw new \RuntimeException("Facture liée introuvable: {$headerData['invoice_number']}");
            }
            $attributes['invoice_id'] = $invoice->id;
        }

        if ($type === 'supplier-invoices') {
            $attributes['invoice_number'] = $documentNumber;
        }

        /** @var \Illuminate\Database\Eloquent\Model $document */
        $document = $modelClass::create($attributes);

        $subtotal = 0;
        foreach ($items as $item) {
            $lineTotal = $this->calculateLineTotal($item);
            $document->{$config['item_relation']}()->create(array_merge($item, [
                'line_total' => $lineTotal,
            ]));
            $subtotal += $lineTotal;
        }

        $document->update([
            'subtotal' => $subtotal,
            'total' => $subtotal + (float) ($headerData['adjustment'] ?? 0),
        ]);
    }

    private function extractHeaderData(array $config, array $row, int $line): array
    {
        $headerData = [];

        foreach ($config['columns'] as $columnKey => $columnConfig) {
            if (in_array($columnKey, ['ref', 'designation', 'description', 'quantite', 'prix_unitaire', 'taux_tva', 'remise'], true)) {
                continue;
            }

            if (in_array($columnKey, ['reference_import', 'client', 'fournisseur'], true)) {
                continue;
            }

            $field = $columnConfig['field'] ?? null;
            if (! $field) {
                continue;
            }

            $value = $row[$columnKey] ?? null;

            if (($columnConfig['required'] ?? false) && $this->isBlank($value)) {
                throw new \RuntimeException("Ligne {$line}: {$columnKey} est obligatoire.");
            }

            if ($this->isBlank($value)) {
                if (array_key_exists('default', $columnConfig)) {
                    $headerData[$field] = $columnConfig['default'];
                }
                continue;
            }

            if (str_contains($field, 'date') || str_contains($columnKey, 'date')) {
                $headerData[$field] = $this->parseDate($value);
                continue;
            }

            if ($field === 'adjustment') {
                $headerData[$field] = (float) $value;
                continue;
            }

            $headerData[$field] = $value;
        }

        return $headerData;
    }

    private function extractItemData(array $config, array $row, int $line): array
    {
        $item = [];

        foreach (['ref', 'designation', 'description', 'quantite', 'prix_unitaire', 'taux_tva', 'remise'] as $columnKey) {
            $columnConfig = $config['columns'][$columnKey];
            $field = $columnConfig['field'];
            $value = $row[$columnKey] ?? null;

            if (($columnConfig['required'] ?? false) && $this->isBlank($value)) {
                throw new \RuntimeException("Ligne {$line}: {$columnKey} est obligatoire.");
            }

            if ($this->isBlank($value)) {
                if (array_key_exists('default', $columnConfig)) {
                    $item[$field] = $columnConfig['default'];
                } else {
                    $item[$field] = null;
                }
                continue;
            }

            if (in_array($field, ['quantity'], true)) {
                $item[$field] = (int) $value;
            } elseif (in_array($field, ['unit_price', 'tax_rate', 'discount'], true)) {
                $item[$field] = (float) $value;
            } else {
                $item[$field] = $value;
            }
        }

        if (! empty($item['ref'])) {
            $product = Product::where('ref', $item['ref'])->first();
            if ($product) {
                $item['product_id'] = $product->id;
            }
        }

        return $item;
    }

    private function resolveParty(array $config, array $row, int $line): int
    {
        if ($config['party_type'] === 'client') {
            $name = trim((string) ($row['client'] ?? ''));
            if ($name === '') {
                throw new \RuntimeException("Ligne {$line}: client est obligatoire.");
            }

            $client = Client::where('name', $name)->first();
            if (! $client) {
                throw new \RuntimeException("Ligne {$line}: client introuvable ({$name}).");
            }

            return $client->id;
        }

        $name = trim((string) ($row['fournisseur'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException("Ligne {$line}: fournisseur est obligatoire.");
        }

        $supplier = Supplier::where('name', $name)->first();
        if (! $supplier) {
            throw new \RuntimeException("Ligne {$line}: fournisseur introuvable ({$name}).");
        }

        return $supplier->id;
    }

    private function resolveDocumentNumber(array $config, array &$headerData): string
    {
        if ($config['number_type'] === 'supplier_invoice') {
            if (! empty($headerData['invoice_number'])) {
                $number = $headerData['invoice_number'];
                if (SupplierInvoice::where('invoice_number', $number)->exists()) {
                    throw new \RuntimeException("Numéro de facture déjà utilisé: {$number}");
                }

                return $number;
            }

            return $this->generateSupplierInvoiceNumber();
        }

        return DocumentNumberService::generate($config['number_type']);
    }

    private function generateSupplierInvoiceNumber(): string
    {
        $count = SupplierInvoice::whereYear('created_at', date('Y'))->count() + 1;

        do {
            $number = 'FSI-' . date('Y') . '/' . str_pad($count, 6, '0', STR_PAD_LEFT);
            $count++;
        } while (SupplierInvoice::where('invoice_number', $number)->exists());

        return $number;
    }

    private function calculateLineTotal(array $item): float
    {
        $lineTotal = $item['quantity'] * $item['unit_price'];
        $lineTotal -= $item['discount'] ?? 0;
        $lineTotal += $lineTotal * (($item['tax_rate'] ?? 0) / 100);

        return round($lineTotal, 2);
    }

    private function parseDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $stringValue = trim((string) $value);
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $stringValue);
            if ($date && $date->format($format) === $stringValue) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($stringValue);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        throw new \RuntimeException("Date invalide: {$stringValue}");
    }

    private function normalizeCellValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    private function normalizeKey(mixed $value): string
    {
        $key = strtolower(trim((string) $value));
        $key = str_replace([' ', '-'], '_', $key);

        return $key;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (! $this->isBlank($value)) {
                return false;
            }
        }

        return true;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
