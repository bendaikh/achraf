<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class RepairProductImages extends Command
{
    protected $signature = 'products:repair-images {--dry-run : Show what would change without writing}';

    protected $description = 'Repair product image paths that point to missing local files (404s)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $repairedLocal = 0;
        $reDownloaded = 0;
        $cleared = 0;
        $unchanged = 0;

        Product::query()
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($dryRun, &$repairedLocal, &$reDownloaded, &$cleared, &$unchanged) {
                foreach ($products as $product) {
                    if (Storage::disk('public')->exists($product->image)) {
                        $unchanged++;

                        continue;
                    }

                    $externalId = (string) ($product->external_id ?? '');
                    $alternate = $this->findAlternateLocalImage($externalId, $product->image);

                    if ($alternate) {
                        $this->line("Local fallback #{$product->id}: {$product->image} → {$alternate}");
                        if (! $dryRun) {
                            $product->forceFill(['image' => $alternate])->save();
                        }
                        $repairedLocal++;

                        continue;
                    }

                    if (filled($product->shopify_image_url)) {
                        $downloaded = $dryRun
                            ? 'products/shopify-'.$externalId.'-preview.jpg'
                            : $this->downloadFromShopify($product->shopify_image_url, $externalId ?: (string) $product->id);

                        if ($downloaded) {
                            $this->line("Re-download #{$product->id}: {$product->image} → {$downloaded}");
                            if (! $dryRun) {
                                $product->forceFill(['image' => $downloaded])->save();
                            }
                            $reDownloaded++;

                            continue;
                        }
                    }

                    $this->warn("Clear broken path #{$product->id}: {$product->image}");
                    if (! $dryRun) {
                        $product->forceFill(['image' => null])->save();
                    }
                    $cleared++;
                }
            });

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '').'Done.');
        $this->table(
            ['Local fallback', 'Re-downloaded', 'Cleared', 'Already OK'],
            [[$repairedLocal, $reDownloaded, $cleared, $unchanged]]
        );

        return self::SUCCESS;
    }

    private function findAlternateLocalImage(string $externalId, string $currentPath): ?string
    {
        if ($externalId === '') {
            return null;
        }

        $files = glob(storage_path('app/public/products/shopify-'.$externalId.'-*')) ?: [];
        $files = array_values(array_filter(
            $files,
            fn ($file) => basename($file) !== basename($currentPath)
        ));

        if ($files === []) {
            return null;
        }

        natsort($files);
        $latest = end($files);

        return $latest ? 'products/'.basename($latest) : null;
    }

    private function downloadFromShopify(string $url, string $productId): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);
            if (! $response->successful() || $response->body() === '') {
                return null;
            }

            $extension = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (! $extension || ! in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $extension = 'jpg';
            }

            $path = 'products/shopify-'.$productId.'-'.time().'.'.$extension;
            if (! Storage::disk('public')->put($path, $response->body())) {
                return null;
            }

            return Storage::disk('public')->exists($path) ? $path : null;
        } catch (\Throwable $e) {
            $this->error('Download failed for '.$productId.': '.$e->getMessage());

            return null;
        }
    }
}
