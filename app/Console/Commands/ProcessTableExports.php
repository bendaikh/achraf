<?php

namespace App\Console\Commands;

use App\Models\TableExport;
use App\Services\BulkCommercialPdfExportService;
use App\Services\TableExportService;
use Illuminate\Console\Command;
use Throwable;

class ProcessTableExports extends Command
{
    protected $signature = 'exports:process {--max=2 : Maximum exports to process per run}';

    protected $description = 'Process pending table exports in the background';

    public function __construct(
        protected TableExportService $exportService,
        protected BulkCommercialPdfExportService $pdfZipService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $max = max(1, (int) $this->option('max'));

        $exports = TableExport::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($max)
            ->get();

        foreach ($exports as $export) {
            $claimed = TableExport::query()
                ->whereKey($export->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'processing',
                    'progress' => 1,
                ]);

            if (! $claimed) {
                continue;
            }

            $export->refresh();

            try {
                $this->processExport($export);
            } catch (Throwable $exception) {
                $export->update([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ]);

                report($exception);
            }
        }

        return self::SUCCESS;
    }

    protected function processExport(TableExport $export): void
    {
        $type = $export->type;
        $ids = $export->ids ?? [];

        if ($ids === []) {
            throw new \RuntimeException('Aucun élément à exporter.');
        }

        if (str_ends_with($type, '-zip')) {
            $this->processZipExport($export, str_replace('-zip', '', $type), $ids);
        } else {
            $this->exportService->processExport($export);
        }
    }

    protected function processZipExport(TableExport $export, string $type, array $ids): void
    {
        if (! $this->pdfZipService->supportsZip($type)) {
            throw new \RuntimeException('Type d\'export ZIP invalide.');
        }

        $export->update([
            'status' => 'processing',
            'progress' => 5,
            'total_rows' => count($ids),
        ]);

        $file = $this->pdfZipService->exportZipToStorage($type, $ids, function (int $progress) use ($export) {
            $export->update(['progress' => max(5, min(95, $progress))]);
        });

        $export->update([
            'status' => 'completed',
            'progress' => 100,
            'filename' => $file['filename'],
            'path' => $file['path'],
            'completed_at' => now(),
        ]);
    }
}
