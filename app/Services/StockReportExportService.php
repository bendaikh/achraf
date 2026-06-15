<?php

namespace App\Services;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockReportExportService
{
    use FiltersIndexTables;

    /**
     * @return Builder<Product>
     */
    public function filteredQuery(string $type, Request $request): Builder
    {
        $query = Product::query()->orderBy('name');

        if ($type === 'enligne') {
            $query->where('source', 'shopify');
            $stockField = 'stock_enligne';
        } else {
            $query->where(function ($q) {
                $q->whereNull('source')->orWhere('source', '!=', 'shopify');
            });
            $stockField = 'stock_magasin';
        }

        if ($request->filled('q') && ! $request->filled('search')) {
            $request->merge(['search' => $request->input('q')]);
        }

        $this->applyTableSearch($query, $request, ['name', 'ref', 'barcode']);

        if ($request->get('filter') === 'low') {
            $query->where(function ($q) use ($stockField) {
                $q->where(function ($q2) use ($stockField) {
                    $q2->whereNotNull('minimum_alert_stock')
                        ->whereColumn($stockField, '<=', 'minimum_alert_stock');
                })->orWhere(function ($q2) use ($stockField) {
                    $q2->whereNull('minimum_alert_stock')
                        ->whereNotNull('minimum_safety_stock')
                        ->whereColumn($stockField, '<=', 'minimum_safety_stock');
                });
            });
        }

        if ($request->filled('ids')) {
            $ids = array_filter(array_map('intval', (array) $request->input('ids')));
            if ($ids !== []) {
                $query->whereIn('id', $ids);
            }
        }

        return $query;
    }

    public function exportExcel(string $type, Request $request): StreamedResponse
    {
        $stockField = $type === 'enligne' ? 'stock_enligne' : 'stock_magasin';
        $label = $type === 'enligne' ? 'Stock en ligne' : 'Stock magasin';
        $products = $this->filteredQuery($type, $request)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($label);

        $headers = ['Référence', 'Produit', $label, 'Prix d\'achat', 'Prix de vente', 'Seuil alerte', 'Seuil sécurité', 'État'];
        if ($type !== 'magasin') {
            $headers = ['Référence', 'Produit', $label, 'Seuil alerte', 'Seuil sécurité', 'État'];
        }
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $rowNum = 2;
        $totalStock = 0;
        foreach ($products as $product) {
            $qty = (int) ($product->{$stockField} ?? 0);
            $totalStock += $qty;
            $sheet->setCellValue([1, $rowNum], $product->ref);
            $sheet->setCellValue([2, $rowNum], $product->name);
            $sheet->setCellValue([3, $rowNum], $qty);
            if ($type === 'magasin') {
                $sheet->setCellValue([4, $rowNum], $product->cost_price_ht);
                $sheet->setCellValue([5, $rowNum], $product->sale_price);
                $sheet->setCellValue([6, $rowNum], $product->minimum_alert_stock);
                $sheet->setCellValue([7, $rowNum], $product->minimum_safety_stock);
                $sheet->setCellValue([8, $rowNum], $this->stockStateLabel($product, $stockField));
            } else {
                $sheet->setCellValue([4, $rowNum], $product->minimum_alert_stock);
                $sheet->setCellValue([5, $rowNum], $product->minimum_safety_stock);
                $sheet->setCellValue([6, $rowNum], $this->stockStateLabel($product, $stockField));
            }
            $rowNum++;
        }

        $sheet->setCellValue([2, $rowNum], 'TOTAL');
        $sheet->setCellValue([3, $rowNum], $totalStock);

        $filename = 'stock-'.$type.'-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(string $type, Request $request)
    {
        $stockField = $type === 'enligne' ? 'stock_enligne' : 'stock_magasin';
        $products = $this->filteredQuery($type, $request)->get();
        $title = $type === 'enligne' ? 'Rapport Stock En Ligne' : 'Rapport Stock Magasin';
        $totalStock = $products->sum(fn (Product $p) => (int) ($p->{$stockField} ?? 0));

        $pdf = Pdf::loadView('stock.reports.pdf', [
            'title' => $title,
            'stockField' => $stockField,
            'stockLabel' => $type === 'enligne' ? 'Stock en ligne' : 'Stock magasin',
            'reportType' => $type,
            'products' => $products,
            'totalStock' => $totalStock,
            'filters' => $this->filterSummary($request),
        ]);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('stock-'.$type.'-'.now()->format('Y-m-d-His').'.pdf');
    }

    protected function stockStateLabel(Product $product, string $stockField): string
    {
        $qty = (int) ($product->{$stockField} ?? 0);
        if ($qty <= 0) {
            return 'Rupture';
        }
        $alert = $product->minimum_alert_stock ?? $product->minimum_safety_stock;
        if ($alert !== null && $qty <= $alert) {
            return 'Sous seuil';
        }

        return 'OK';
    }

    protected function filterSummary(Request $request): string
    {
        $parts = [];
        if ($request->filled('search') || $request->filled('q')) {
            $parts[] = 'Recherche: '.($request->input('search') ?: $request->input('q'));
        }
        if ($request->get('filter') === 'low') {
            $parts[] = 'Sous seuil uniquement';
        }

        return $parts === [] ? 'Tous les produits' : implode(' · ', $parts);
    }
}
