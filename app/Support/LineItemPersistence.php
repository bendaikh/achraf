<?php

namespace App\Support;

use App\Models\Product;
use App\Support\LineItemCalculator;
use Illuminate\Database\Eloquent\Model;

class LineItemPersistence
{
    /**
     * @param  array<string, mixed>  $item
     * @return array{discount: float|int, discount_type: ?string, line_total: float|int}
     */
    public static function createPurchaseItem(Model $document, array $item): array
    {
        $item = VariantLineItem::normalize($item);
        $computed = LineItemCalculator::compute($item);

        $document->items()->create([
            'product_id' => $item['product_id'] ?? null,
            'product_variant_id' => $item['product_variant_id'] ?? null,
            'ref' => $item['ref'] ?? null,
            'designation' => $item['designation'],
            'description' => $item['description'] ?? null,
            'source_document_reference' => $item['source_document_reference'] ?? null,
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'tax_rate' => $item['tax_rate'],
            'discount' => $computed['discount'],
            'discount_type' => $computed['discount_type'],
            'line_total' => $computed['line_total'],
        ]);

        return $computed;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{discount: float|int, discount_type: ?string, line_total: float|int}
     */
    public static function createInvoiceItem(Model $document, array $item): array
    {
        $item = VariantLineItem::normalize($item);
        $computed = LineItemCalculator::compute($item);

        $document->items()->create([
            'product_id' => $item['product_id'] ?? null,
            'product_variant_id' => $item['product_variant_id'] ?? null,
            'ref' => $item['ref'] ?? null,
            'designation' => $item['designation'],
            'description' => $item['description'] ?? null,
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'tax_rate' => $item['tax_rate'],
            'discount' => $computed['discount'],
            'discount_type' => $computed['discount_type'],
            'line_total' => $computed['line_total'],
        ]);

        return $computed;
    }
}
