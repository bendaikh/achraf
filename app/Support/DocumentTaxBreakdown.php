<?php

namespace App\Support;

use Illuminate\Support\Collection;

class DocumentTaxBreakdown
{
  /**
   * @param  iterable<object|array<string, mixed>>  $items
   * @return array{subtotal_ht: float, tax_total: float, total_ttc: float, document_discount: float, adjustment: float}
   */
  public static function fromItems(
    iterable $items,
    float $adjustment = 0,
    float $documentDiscount = 0,
  ): array {
    $subtotalHt = 0.0;
    $taxTotal = 0.0;

    foreach ($items as $item) {
      $quantity = (float) (is_array($item) ? ($item['quantity'] ?? 0) : $item->quantity);
      $unitPrice = (float) (is_array($item) ? ($item['unit_price'] ?? 0) : $item->unit_price);
      $discount = (float) (is_array($item) ? ($item['discount'] ?? 0) : ($item->discount ?? 0));
      $taxRate = (float) (is_array($item) ? ($item['tax_rate'] ?? 0) : ($item->tax_rate ?? 0));

      $lineHt = max(0, ($quantity * $unitPrice) - $discount);
      $lineTax = $lineHt * ($taxRate / 100);

      $subtotalHt += $lineHt;
      $taxTotal += $lineTax;
    }

    $totalTtc = $subtotalHt + $taxTotal - $documentDiscount + $adjustment;

    return [
      'subtotal_ht' => round($subtotalHt, 2),
      'tax_total' => round($taxTotal, 2),
      'total_ttc' => round(max(0, $totalTtc), 2),
      'document_discount' => round($documentDiscount, 2),
      'adjustment' => round($adjustment, 2),
    ];
  }

  /**
   * @param  Collection<int, object>  $items
   */
  public static function fromDocument(object $document, Collection $items): array
  {
    return static::fromItems(
      $items,
      (float) ($document->adjustment ?? 0),
      (float) ($document->discount ?? 0),
    );
  }
}
