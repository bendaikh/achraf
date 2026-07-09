<?php

namespace App\Services\Jumia;

use App\Models\JumiaIntegration;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class JumiaStockSyncService
{
    public function __construct(
        protected JumiaApiClient $client,
        protected JumiaIntegration $integration,
    ) {}

    /**
     * Synchronize local stock quantities to Jumia for all eligible products.
     *
     * @param  array{
     *     sku?: string|null,
     *     batch_size?: int,
     *     delay_ms?: int,
     *     dry_run?: bool,
     *     max_retries?: int,
     * }  $options
     */
    public function sync(array $options = []): JumiaStockSyncResult
    {
        $batchSize = max(1, (int) ($options['batch_size'] ?? 20));
        $delayMs = max(0, (int) ($options['delay_ms'] ?? 500));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $maxRetries = max(1, (int) ($options['max_retries'] ?? 3));

        $result = new JumiaStockSyncResult;
        $products = $this->loadProducts($options['sku'] ?? null);

        if ($products->isEmpty()) {
            Log::info('Jumia stock sync: no products to process.');

            return $result;
        }

        Log::info('Jumia stock sync started', [
            'product_count' => $products->count(),
            'batch_size' => $batchSize,
            'dry_run' => $dryRun,
        ]);

        $chunks = $products->chunk($batchSize);
        $lastChunkIndex = $chunks->count() - 1;

        foreach ($chunks as $batchIndex => $batch) {
            $this->processBatch($batch, $result, $dryRun, $maxRetries);

            if ($delayMs > 0 && $batchIndex < $lastChunkIndex) {
                usleep($delayMs * 1000);
            }
        }

        Log::info('Jumia stock sync completed', [
            'checked' => $result->totalChecked,
            'updated' => $result->totalUpdated,
            'already_synced' => $result->totalAlreadySynced,
            'not_found' => $result->totalNotFound,
            'errors' => $result->totalErrors,
        ]);

        return $result;
    }

    public static function makeFromIntegration(JumiaIntegration $integration): self
    {
        return new self(new JumiaApiClient($integration), $integration);
    }

    /**
     * @return Collection<int, Product>
     */
    protected function loadProducts(?string $sku): Collection
    {
        $query = Product::query()
            ->whereNotNull('ref')
            ->where('ref', '!=', '')
            ->orderBy('id');

        if ($sku !== null && trim($sku) !== '') {
            $query->whereRaw('LOWER(ref) = ?', [strtolower(trim($sku))]);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Product>  $batch
     */
    protected function processBatch(
        Collection $batch,
        JumiaStockSyncResult $result,
        bool $dryRun,
        int $maxRetries
    ): void {
        /** @var array<int, array{product: Product, local_stock: int, jumia_stock: int, variation_id: ?string}> $toUpdate */
        $toUpdate = [];

        foreach ($batch as $product) {
            $sku = trim((string) $product->ref);
            $localStock = max(0, (int) $product->stock_enligne);
            $productName = (string) $product->name;

            try {
                $jumiaData = $this->withRetry(
                    fn () => $this->client->getStockForSellerSku($sku),
                    $maxRetries
                );

                if ($jumiaData === null) {
                    $entry = new JumiaStockSyncLogEntry(
                        sku: $sku,
                        productName: $productName,
                        localStock: $localStock,
                        jumiaStock: null,
                        status: JumiaStockSyncLogEntry::STATUS_NOT_FOUND,
                    );
                    $result->addEntry($entry);
                    $this->logEntry($entry);

                    continue;
                }

                $jumiaStock = (int) $jumiaData['stock'];
                $variationId = $jumiaData['variation_id'] ?? $product->jumia_product_sid;

                if ($jumiaStock === $localStock) {
                    $entry = new JumiaStockSyncLogEntry(
                        sku: $sku,
                        productName: $productName,
                        localStock: $localStock,
                        jumiaStock: $jumiaStock,
                        status: JumiaStockSyncLogEntry::STATUS_ALREADY_SYNCED,
                    );
                    $result->addEntry($entry);
                    $this->logEntry($entry);

                    continue;
                }

                if ($dryRun) {
                    $entry = new JumiaStockSyncLogEntry(
                        sku: $sku,
                        productName: $productName,
                        localStock: $localStock,
                        jumiaStock: $jumiaStock,
                        status: JumiaStockSyncLogEntry::STATUS_UPDATED,
                        message: 'Dry run — no update sent.',
                    );
                    $result->addEntry($entry);
                    $this->logEntry($entry);

                    continue;
                }

                $toUpdate[] = [
                    'product' => $product,
                    'local_stock' => $localStock,
                    'jumia_stock' => $jumiaStock,
                    'variation_id' => $variationId !== null ? (string) $variationId : null,
                ];
            } catch (\Throwable $e) {
                $entry = new JumiaStockSyncLogEntry(
                    sku: $sku,
                    productName: $productName,
                    localStock: $localStock,
                    jumiaStock: null,
                    status: JumiaStockSyncLogEntry::STATUS_ERROR,
                    message: $e->getMessage(),
                );
                $result->addEntry($entry);
                $this->logEntry($entry);
            }
        }

        if ($toUpdate === [] || $dryRun) {
            return;
        }

        $this->pushBatchUpdates($toUpdate, $result, $maxRetries);
    }

    /**
     * @param  array<int, array{product: Product, local_stock: int, jumia_stock: int, variation_id: ?string}>  $toUpdate
     */
    protected function pushBatchUpdates(array $toUpdate, JumiaStockSyncResult $result, int $maxRetries): void
    {
        $payload = array_map(static function (array $row): array {
            return [
                'sellerSku' => trim((string) $row['product']->ref),
                'stock' => $row['local_stock'],
                'variationId' => $row['variation_id'],
            ];
        }, $toUpdate);

        try {
            $response = $this->withRetry(
                fn () => $this->client->updateProductStockBatch($payload),
                $maxRetries
            );

            if (! isset($response['feedId'])) {
                throw new \RuntimeException('Invalid Jumia response: feedId missing.');
            }

            $feedId = $response['feedId'];

            foreach ($toUpdate as $row) {
                $product = $row['product'];
                $sku = trim((string) $product->ref);

                if ($row['variation_id'] !== null && $row['variation_id'] !== '') {
                    $product->forceFill(['jumia_product_sid' => $row['variation_id']]);
                }

                $product->forceFill(['jumia_stock_synced_at' => now()])->save();

                $entry = new JumiaStockSyncLogEntry(
                    sku: $sku,
                    productName: (string) $product->name,
                    localStock: $row['local_stock'],
                    jumiaStock: $row['jumia_stock'],
                    status: JumiaStockSyncLogEntry::STATUS_UPDATED,
                    message: 'feedId: '.$feedId,
                );
                $result->addEntry($entry);
                $this->logEntry($entry);
            }
        } catch (\Throwable $e) {
            foreach ($toUpdate as $row) {
                $product = $row['product'];
                $sku = trim((string) $product->ref);

                $entry = new JumiaStockSyncLogEntry(
                    sku: $sku,
                    productName: (string) $product->name,
                    localStock: $row['local_stock'],
                    jumiaStock: $row['jumia_stock'],
                    status: JumiaStockSyncLogEntry::STATUS_ERROR,
                    message: $e->getMessage(),
                );
                $result->addEntry($entry);
                $this->logEntry($entry);
            }
        }
    }

    protected function logEntry(JumiaStockSyncLogEntry $entry): void
    {
        $context = [
            'sku' => $entry->sku,
            'product_name' => $entry->productName,
            'local_stock' => $entry->localStock,
            'jumia_stock' => $entry->jumiaStock,
            'status' => $entry->status,
        ];

        if ($entry->message !== null) {
            $context['message'] = $entry->message;
        }

        match ($entry->status) {
            JumiaStockSyncLogEntry::STATUS_ERROR,
            JumiaStockSyncLogEntry::STATUS_NOT_FOUND => Log::warning('Jumia stock sync', $context),
            default => Log::info('Jumia stock sync', $context),
        };
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function withRetry(callable $callback, int $maxAttempts): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt >= $maxAttempts || ! $this->isRetryable($e)) {
                    throw $e;
                }

                usleep(250_000 * $attempt);
            }
        }

        throw $lastException ?? new \RuntimeException('Jumia stock sync retry failed.');
    }

    protected function isRetryable(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, '429')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'timeout')
            || str_contains($message, 'temporarily unavailable')
        ) {
            return true;
        }

        if (preg_match('/http error 5\d\d/', $message) === 1) {
            return true;
        }

        return false;
    }
}
