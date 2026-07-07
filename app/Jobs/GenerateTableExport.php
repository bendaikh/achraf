<?php

namespace App\Jobs;

use App\Models\TableExport;
use App\Services\TableExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateTableExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $connection = 'database';

    public int $timeout = 600;

    /**
     * @param  array<int, int>  $ids
     */
    public function __construct(
        public int $exportId,
        public string $type,
        public array $ids,
    ) {}

    public function handle(TableExportService $exportService): void
    {
        $export = TableExport::findOrFail($this->exportId);

        if ($export->ids === null) {
            $export->update(['ids' => $this->ids]);
        }

        $exportService->processExport($export);
    }

    public function failed(Throwable $exception): void
    {
        TableExport::whereKey($this->exportId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
