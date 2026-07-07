<?php

namespace App\Console\Commands;

use App\Models\TableExport;
use App\Services\TableExportService;
use Illuminate\Console\Command;
use Throwable;

class ProcessTableExports extends Command
{
    protected $signature = 'exports:process {--max=2 : Maximum exports to process per run}';

    protected $description = 'Process pending table exports in the background';

    public function handle(TableExportService $exportService): int
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
                $exportService->processExport($export);
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
}
