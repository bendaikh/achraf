<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductCompatibilityService
{
    /**
     * Sync compatible products bidirectionally for a product.
     * Does not merge SKUs or stocks — links only.
     *
     * @param  list<int|string>  $compatibleIds
     */
    public function sync(Product $product, array $compatibleIds): void
    {
        $targetIds = collect($compatibleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== (int) $product->id)
            ->unique()
            ->values();

        $existingIds = $this->compatibleIdsFor($product->id);

        $toAttach = $targetIds->diff($existingIds)->values();
        $toDetach = $existingIds->diff($targetIds)->values();

        DB::transaction(function () use ($product, $toAttach, $toDetach) {
            foreach ($toDetach as $otherId) {
                $this->unlinkPair((int) $product->id, (int) $otherId);
            }
            foreach ($toAttach as $otherId) {
                $this->linkPair((int) $product->id, (int) $otherId);
            }
        });
    }

    public function unlink(Product $product, Product $other): void
    {
        $this->unlinkPair((int) $product->id, (int) $other->id);
    }

    /**
     * @return Collection<int, int>
     */
    public function compatibleIdsFor(int $productId): Collection
    {
        return DB::table('product_compatibilities')
            ->where('product_id', $productId)
            ->pluck('compatible_product_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * Product IDs compatible with any of the given product IDs (excluding the given set).
     *
     * @param  Collection<int, int>|list<int>  $productIds
     * @return Collection<int, int>
     */
    public function compatibleIdsOfMany($productIds): Collection
    {
        $ids = collect($productIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('product_compatibilities')
            ->whereIn('product_id', $ids)
            ->pluck('compatible_product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->diff($ids)
            ->values();
    }

    protected function linkPair(int $a, int $b): void
    {
        if ($a === $b) {
            return;
        }

        $now = now();
        foreach ([[$a, $b], [$b, $a]] as [$from, $to]) {
            $exists = DB::table('product_compatibilities')
                ->where('product_id', $from)
                ->where('compatible_product_id', $to)
                ->exists();

            if (! $exists) {
                DB::table('product_compatibilities')->insert([
                    'product_id' => $from,
                    'compatible_product_id' => $to,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    protected function unlinkPair(int $a, int $b): void
    {
        DB::table('product_compatibilities')
            ->where(function ($q) use ($a, $b) {
                $q->where('product_id', $a)->where('compatible_product_id', $b);
            })
            ->orWhere(function ($q) use ($a, $b) {
                $q->where('product_id', $b)->where('compatible_product_id', $a);
            })
            ->delete();
    }
}
