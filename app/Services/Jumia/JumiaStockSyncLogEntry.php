<?php

namespace App\Services\Jumia;

class JumiaStockSyncLogEntry
{
    public const STATUS_UPDATED = 'Updated';

    public const STATUS_ALREADY_SYNCED = 'Already Synced';

    public const STATUS_NOT_FOUND = 'Product Not Found';

    public const STATUS_ERROR = 'Error';

    public function __construct(
        public readonly string $sku,
        public readonly string $productName,
        public readonly int $localStock,
        public readonly ?int $jumiaStock,
        public readonly string $status,
        public readonly ?string $message = null,
    ) {}
}
