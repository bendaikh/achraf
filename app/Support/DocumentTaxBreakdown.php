<?php

namespace App\Support;

use Illuminate\Support\Collection;

class DocumentTaxBreakdown
{
  /**
   * @param  iterable<object|array<string, mixed>>  $items
   * @param  iterable<object|array<string, mixed>>  $adjustments
   * @return array{
   *     subtotal_ht: float,
   *     tax_total: float,
   *     total_ttc: float,
   *     document_discount: float,
   *     adjustment: float,
   *     items_subtotal_ht: float,
   *     items_tax_total: float,
   *     items_ttc: float,
   *     adjustments_positive: float,
   *     adjustments_negative: float,
   *     adjustment_tax_total: float,
   *     adjustment_ht_total: float,
   *     adjustment_lines: list<array<string, mixed>>
   * }
   */
  public static function fromItems(
    iterable $items,
    float $adjustment = 0,
    float $documentDiscount = 0,
    string $priceMode = 'sale',
    iterable $adjustments = [],
  ): array {
    $subtotalHt = 0.0;
    $taxTotal = 0.0;

    foreach ($items as $item) {
      $quantity = (float) (is_array($item) ? ($item['quantity'] ?? 0) : $item->quantity);
      $unitPrice = LineItemCalculator::effectiveUnitPriceForBreakdown($item, $priceMode);
      $discountInput = (float) (is_array($item) ? ($item['discount'] ?? 0) : ($item->discount ?? 0));
      $discountType = (is_array($item) ? ($item['discount_type'] ?? 'fixed') : ($item->discount_type ?? 'fixed'));
      $taxRate = (float) (is_array($item) ? ($item['tax_rate'] ?? 0) : ($item->tax_rate ?? 0));

      $breakdown = LineItemCalculator::breakdown(
        quantity: $quantity,
        unitPrice: $unitPrice,
        taxRate: $taxRate,
        discountInput: $discountInput,
        discountType: $discountType,
        priceMode: $priceMode,
      );

      $subtotalHt += $breakdown['line_ht'];
      $taxTotal += $breakdown['line_tax'];
    }

    $itemsSubtotalHt = round($subtotalHt, 2);
    $itemsTaxTotal = round($taxTotal, 2);
    $itemsTtc = round($itemsSubtotalHt + $itemsTaxTotal - $documentDiscount, 2);

    $positive = 0.0;
    $negative = 0.0;
    $adjustmentTax = 0.0;
    $adjustmentHt = 0.0;
    $lines = [];
    $hasLines = false;

    foreach ($adjustments as $row) {
      $hasLines = true;
      $computed = DocumentAdjustmentCalculator::compute($row);
      $label = is_array($row) ? (string) ($row['label'] ?? '') : (string) ($row->label ?? '');
      $sign = $computed['type'] === DocumentAdjustmentCalculator::TYPE_DEDUCT ? -1.0 : 1.0;

      if ($computed['type'] === DocumentAdjustmentCalculator::TYPE_DEDUCT) {
        $negative += $computed['line_total'];
      } else {
        $positive += $computed['line_total'];
      }

      $adjustmentHt += $sign * $computed['amount'];
      $adjustmentTax += $sign * $computed['tax'];

      $lines[] = [
        'label' => $label,
        'type' => $computed['type'],
        'amount' => $computed['amount'],
        'is_taxable' => $computed['is_taxable'],
        'tax_rate' => $computed['tax_rate'],
        'tax' => $computed['tax'],
        'line_total' => $computed['line_total'],
        'signed_total' => $computed['signed_total'],
      ];
    }

    $netAdjustment = $hasLines
      ? round($positive - $negative, 2)
      : round($adjustment, 2);

    $totalTtc = $itemsTtc + $netAdjustment;

    return [
      'subtotal_ht' => $itemsSubtotalHt,
      'tax_total' => $itemsTaxTotal,
      'total_ttc' => round(max(0, $totalTtc), 2),
      'document_discount' => round($documentDiscount, 2),
      'adjustment' => $netAdjustment,
      'items_subtotal_ht' => $itemsSubtotalHt,
      'items_tax_total' => $itemsTaxTotal,
      'items_ttc' => round(max(0, $itemsTtc), 2),
      'adjustments_positive' => round($positive, 2),
      'adjustments_negative' => round($negative, 2),
      'adjustment_tax_total' => round($adjustmentTax, 2),
      'adjustment_ht_total' => round($adjustmentHt, 2),
      'adjustment_lines' => $lines,
    ];
  }

  /**
   * @param  Collection<int, object>  $items
   */
  public static function fromDocument(object $document, Collection $items): array
  {
    $adjustments = [];

    if (method_exists($document, 'adjustments')) {
      $adjustments = $document->relationLoaded('adjustments')
        ? $document->adjustments
        : $document->adjustments()->get();
    }

    return static::fromItems(
      $items,
      (float) ($document->adjustment ?? 0),
      (float) ($document->discount ?? 0),
      LineItemCalculator::priceModeForDocument($document),
      $adjustments,
    );
  }
}
