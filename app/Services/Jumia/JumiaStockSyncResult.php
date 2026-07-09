<?php

namespace App\Services\Jumia;

class JumiaStockSyncResult
{
    public int $totalChecked = 0;

    public int $totalUpdated = 0;

    public int $totalAlreadySynced = 0;

    public int $totalNotFound = 0;

    public int $totalErrors = 0;

    /** @var array<int, JumiaStockSyncLogEntry> */
    public array $entries = [];

    public function addEntry(JumiaStockSyncLogEntry $entry): void
    {
        $this->entries[] = $entry;
        $this->totalChecked++;

        match ($entry->status) {
            JumiaStockSyncLogEntry::STATUS_UPDATED => $this->totalUpdated++,
            JumiaStockSyncLogEntry::STATUS_ALREADY_SYNCED => $this->totalAlreadySynced++,
            JumiaStockSyncLogEntry::STATUS_NOT_FOUND => $this->totalNotFound++,
            JumiaStockSyncLogEntry::STATUS_ERROR => $this->totalErrors++,
            default => null,
        };
    }
}
