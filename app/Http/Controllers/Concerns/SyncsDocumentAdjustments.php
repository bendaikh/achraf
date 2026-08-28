<?php

namespace App\Http\Controllers\Concerns;

use App\Support\DocumentAdjustmentCalculator;
use App\Support\DocumentTaxBreakdown;
use Illuminate\Database\Eloquent\Model;

trait SyncsDocumentAdjustments
{
    /**
     * @return array<string, mixed>
     */
    protected function adjustmentValidationRules(): array
    {
        return [
            'adjustments' => 'nullable|array',
            'adjustments.*.label' => 'required|string|max:255',
            'adjustments.*.type' => 'required|in:add,deduct',
            'adjustments.*.amount' => 'required|numeric|min:0',
            'adjustments.*.is_taxable' => 'nullable',
            'adjustments.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function persistDocumentTotals(Model $document, float $itemsSubtotal, array $rows): void
    {
        $this->syncDocumentAdjustments($document, $rows);
        $document->load('items', 'adjustments');
        $taxes = DocumentTaxBreakdown::fromDocument($document, $document->items);

        $document->update([
            'subtotal' => round($itemsSubtotal, 2),
            'adjustment' => $taxes['adjustment'],
            'total' => $taxes['total_ttc'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncDocumentAdjustments(Model $document, array $rows): void
    {
        $document->adjustments()->delete();

        foreach (array_values($rows) as $index => $row) {
            $computed = DocumentAdjustmentCalculator::compute($row);

            $document->adjustments()->create([
                'label' => trim((string) ($row['label'] ?? '')),
                'type' => $computed['type'],
                'amount' => $computed['amount'],
                'is_taxable' => $computed['is_taxable'],
                'tax_rate' => $computed['tax_rate'],
                'line_total' => $computed['line_total'],
                'sort_order' => $index,
            ]);
        }
    }
}
