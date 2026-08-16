<?php

namespace App\Services\Documents;

use App\Models\ManagedDocument;
use App\Support\DocumentAttachmentRegistry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use ZipArchive;

class DocumentArchiveExportService
{
    public function __construct(
        protected DocumentNamingService $naming,
        protected DocumentPdfMergeService $pdfMerge
    ) {}

    /**
     * @param  list<string>  $sectionKeys
     * @return array{
     *   expected: Collection<int, array<string, mixed>>,
     *   present: Collection<int, ManagedDocument>,
     *   missing: Collection<int, array<string, mixed>>,
     *   expected_count: int,
     *   present_count: int,
     *   missing_count: int
     * }
     */
    public function inspect(string $dateFrom, string $dateTo, array $sectionKeys): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();
        $sectionKeys = array_values(array_intersect($sectionKeys, DocumentAttachmentRegistry::exportableKeys()));

        $expected = collect();
        $present = collect();

        foreach ($sectionKeys as $sectionKey) {
            $config = DocumentAttachmentRegistry::get($sectionKey);
            /** @var class-string<Model> $modelClass */
            $modelClass = $config['model'];
            $query = $modelClass::query();

            if (isset($config['scope']) && is_callable($config['scope'])) {
                ($config['scope'])($query);
            }

            $this->applyDateFilter($query, $sectionKey, $from, $to);

            $records = $query->get();

            foreach ($records as $record) {
                $reference = DocumentAttachmentRegistry::referenceFor($sectionKey, $record);
                $documentDate = DocumentAttachmentRegistry::documentDateFor($sectionKey, $record);
                $docs = ManagedDocument::query()
                    ->where('section_key', $sectionKey)
                    ->where('documentable_type', $record->getMorphClass())
                    ->where('documentable_id', $record->getKey())
                    ->where('is_active', true)
                    ->whereNotNull('current_version_id')
                    ->with('currentVersion')
                    ->get();

                $expected->push([
                    'section_key' => $sectionKey,
                    'section_label' => $config['label'],
                    'record_id' => $record->getKey(),
                    'reference' => $reference,
                    'document_date' => $documentDate?->format('Y-m-d'),
                    'document_date_display' => $documentDate?->format('d/m/Y'),
                    'has_document' => $docs->isNotEmpty(),
                ]);

                foreach ($docs as $doc) {
                    $present->push($doc);
                }
            }
        }

        $missing = $expected->where('has_document', false)->values();
        $orderedPresent = $this->sortDocuments($present);

        return [
            'expected' => $expected->values(),
            'present' => $orderedPresent,
            'missing' => $missing,
            'expected_count' => $expected->count(),
            'present_count' => $orderedPresent->count(),
            'missing_count' => $missing->count(),
        ];
    }

    /**
     * @param  list<string>  $sectionKeys
     */
    public function exportExcel(string $dateFrom, string $dateTo, array $sectionKeys): array
    {
        $inspection = $this->inspect($dateFrom, $dateTo, $sectionKeys);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Documents');
        $sheet->fromArray([
            'Section',
            'Référence',
            'Date document',
            'Statut pièce',
            'Nom fichier',
            'Importé le',
            'Utilisateur',
        ], null, 'A1');

        $row = 2;
        foreach ($inspection['expected'] as $item) {
            $doc = $inspection['present']->first(function (ManagedDocument $document) use ($item) {
                return $document->section_key === $item['section_key']
                    && (int) $document->documentable_id === (int) $item['record_id'];
            });

            $sheet->fromArray([
                $item['section_label'],
                $item['reference'],
                $item['document_date_display'] ?? '',
                $item['has_document'] ? 'Présent' : 'Manquant',
                $doc?->display_name ?? '',
                $doc?->imported_at?->format('d/m/Y H:i') ?? '',
                $doc?->uploader?->name ?? '',
            ], null, 'A'.$row);
            $row++;
        }

        $filename = sprintf(
            'Achats_%s_au_%s.xlsx',
            Carbon::parse($dateFrom)->format('d-m-Y'),
            Carbon::parse($dateTo)->format('d-m-Y')
        );
        $relative = 'exports/document-archive/'.$filename;
        Storage::disk('local')->makeDirectory('exports/document-archive');
        $absolute = Storage::disk('local')->path($relative);
        (new Xlsx($spreadsheet))->save($absolute);

        return [
            'filename' => $filename,
            'disk' => 'local',
            'path' => $relative,
            'inspection' => $inspection,
        ];
    }

    /**
     * @param  list<string>  $sectionKeys
     */
    public function exportZip(string $dateFrom, string $dateTo, array $sectionKeys, bool $allowMissing = false): array
    {
        $inspection = $this->inspect($dateFrom, $dateTo, $sectionKeys);

        if ($inspection['missing_count'] > 0 && ! $allowMissing) {
            return [
                'blocked' => true,
                'inspection' => $inspection,
            ];
        }

        $filename = sprintf(
            'Achats_%s_au_%s.zip',
            Carbon::parse($dateFrom)->format('d-m-Y'),
            Carbon::parse($dateTo)->format('d-m-Y')
        );
        $relative = 'exports/document-archive/'.$filename;
        Storage::disk('local')->makeDirectory('exports/document-archive');
        $absolute = Storage::disk('local')->path($relative);

        $zip = new ZipArchive;
        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer le ZIP.');
        }

        $index = 1;
        foreach ($inspection['present'] as $document) {
            $version = $document->currentVersion;
            if (! $version || ! $version->existsOnDisk()) {
                continue;
            }

            $extension = pathinfo($document->display_name, PATHINFO_EXTENSION) ?: pathinfo($version->path, PATHINFO_EXTENSION) ?: 'bin';
            $entryName = $this->naming->exportPrefix(
                $index,
                $document->document_date?->format('Y-m-d') ?? '0000-00-00',
                $document->reference ?: ('DOC-'.$document->id),
                $extension
            );

            $zip->addFile($version->absolutePath(), $entryName);
            $index++;
        }

        $zip->close();

        return [
            'blocked' => false,
            'filename' => $filename,
            'disk' => 'local',
            'path' => $relative,
            'inspection' => $inspection,
        ];
    }

    /**
     * @param  list<string>  $sectionKeys
     */
    public function exportMergedPdf(string $dateFrom, string $dateTo, array $sectionKeys, bool $allowMissing = false): array
    {
        $inspection = $this->inspect($dateFrom, $dateTo, $sectionKeys);

        if ($inspection['missing_count'] > 0 && ! $allowMissing) {
            return [
                'blocked' => true,
                'inspection' => $inspection,
            ];
        }

        if ($inspection['present']->isEmpty()) {
            throw new RuntimeException('Aucun document présent à fusionner pour cette période.');
        }

        $content = $this->pdfMerge->merge($inspection['present']);
        $filename = sprintf(
            'Achats_%s_au_%s.pdf',
            Carbon::parse($dateFrom)->format('d-m-Y'),
            Carbon::parse($dateTo)->format('d-m-Y')
        );
        $relative = 'exports/document-archive/'.$filename;
        Storage::disk('local')->put($relative, $content);

        return [
            'blocked' => false,
            'filename' => $filename,
            'disk' => 'local',
            'path' => $relative,
            'inspection' => $inspection,
        ];
    }

    /**
     * @param  Collection<int, ManagedDocument>  $documents
     * @return Collection<int, ManagedDocument>
     */
    public function sortDocuments(Collection $documents): Collection
    {
        return $documents
            ->sort(function (ManagedDocument $a, ManagedDocument $b) {
                $dateA = $a->document_date?->format('Y-m-d') ?? '9999-99-99';
                $dateB = $b->document_date?->format('Y-m-d') ?? '9999-99-99';

                if ($dateA !== $dateB) {
                    return $dateA <=> $dateB;
                }

                return strcasecmp((string) $a->reference, (string) $b->reference);
            })
            ->values();
    }

    protected function applyDateFilter(Builder $query, string $sectionKey, Carbon $from, Carbon $to): void
    {
        $column = match ($sectionKey) {
            'expenses-with-invoice', 'expenses-without-invoice' => 'expense_date',
            'supplier-purchase-orders' => 'order_date',
            'supplier-delivery-notes' => 'delivery_date',
            'receptions' => 'reception_date',
            'supplier-invoices' => 'invoice_date',
            'supplier-credit-notes' => 'credit_note_date',
            'supplier-payments' => 'payment_date',
            'delivery-notes' => 'delivery_date',
            default => null,
        };

        if ($column) {
            $query->whereBetween($column, [$from->toDateString(), $to->toDateString()]);
        }
    }
}
