<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PointOfSaleController extends Controller
{
    public function index()
    {
        $clients = Client::orderBy('name')->get();
        $products = Product::query()
            ->where(function ($q) {
                $q->where('status', 'Activer')->orWhereNull('status');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'ref', 'sale_price', 'barcode', 'vat_category']);

        $productsForJs = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'ref' => $p->ref,
            'sale_price' => (float) ($p->sale_price ?? 0),
            'barcode' => $p->barcode,
            'tax_rate' => $this->defaultTaxRate($p),
        ])->values();

        $paymentMethods = PosSale::paymentLabels();

        return view('pos.index', compact('clients', 'products', 'productsForJs', 'paymentMethods'));
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $barcode = trim((string) $request->get('barcode', ''));

        $query = Product::query()->where(function ($q) {
            $q->where('status', 'Activer')->orWhereNull('status');
        });

        if ($barcode !== '') {
            $query->where('barcode', $barcode);
        } elseif ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('ref', 'like', '%'.$q.'%')
                    ->orWhere('barcode', 'like', '%'.$q.'%');
            });
        } else {
            return response()->json(['products' => []]);
        }

        $rows = $query->limit(30)->get(['id', 'name', 'ref', 'sale_price', 'barcode', 'vat_category']);

        $products = $rows->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'ref' => $p->ref,
            'sale_price' => (float) ($p->sale_price ?? 0),
            'barcode' => $p->barcode,
            'tax_rate' => $this->defaultTaxRate($p),
        ])->values();

        return response()->json(['products' => $products]);
    }

    public function checkout(Request $request)
    {
        $request->merge([
            'client_id' => $request->filled('client_id') ? $request->client_id : null,
        ]);

        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount' => 'nullable|numeric|min:0',
            'global_discount' => 'nullable|numeric|min:0',
            'payment_method' => ['required', Rule::in(array_keys(PosSale::paymentLabels()))],
            'amount_received' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validated['payment_method'] === PosSale::PAYMENT_CASH) {
            $request->validate([
                'amount_received' => 'required|numeric|min:0',
            ]);
        }

        DB::beginTransaction();
        try {
            $ticketNumber = 'POS-'.date('Y').'/'.str_pad(
                (string) (PosSale::whereYear('created_at', (int) date('Y'))->count() + 1),
                6,
                '0',
                STR_PAD_LEFT
            );

            $subtotalHt = 0;
            $taxTotal = 0;
            $lineRows = [];

            foreach ($validated['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $qty = (int) $row['quantity'];
                $unitPrice = (float) $row['unit_price'];
                $taxRate = isset($row['tax_rate']) ? (float) $row['tax_rate'] : $this->defaultTaxRate($product);
                $discount = (float) ($row['discount'] ?? 0);

                $base = ($qty * $unitPrice) - $discount;
                if ($base < 0) {
                    $base = 0;
                }
                $tax = round($base * ($taxRate / 100), 2);
                $lineTotal = round($base + $tax, 2);

                $subtotalHt += round($base, 2);
                $taxTotal += $tax;

                $lineRows[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ];
            }

            $globalDiscount = round((float) ($validated['global_discount'] ?? 0), 2);
            $totalBeforeGlobal = round($subtotalHt + $taxTotal, 2);
            $total = max(0, round($totalBeforeGlobal - $globalDiscount, 2));

            $amountReceived = isset($validated['amount_received']) ? (float) $validated['amount_received'] : null;
            $changeAmount = 0;

            if ($validated['payment_method'] === PosSale::PAYMENT_CASH) {
                if ($amountReceived + 0.00001 < $total) {
                    DB::rollBack();

                    return back()->withInput()->with('error', 'Le montant reçu est insuffisant pour un paiement en espèces.');
                }
                $changeAmount = round(max(0, $amountReceived - $total), 2);
            }

            $sale = PosSale::create([
                'ticket_number' => $ticketNumber,
                'client_id' => $validated['client_id'] ?? null,
                'user_id' => $request->user()->id,
                'sold_at' => now(),
                'currency' => 'dh - MAD',
                'subtotal' => $subtotalHt,
                'discount' => $globalDiscount,
                'tax_total' => $taxTotal,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'amount_received' => $amountReceived,
                'change_amount' => $changeAmount,
                'status' => PosSale::STATUS_COMPLETED,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($lineRows as $row) {
                $p = $row['product'];
                $sale->items()->create([
                    'product_id' => $p->id,
                    'ref' => $p->ref,
                    'designation' => $p->name,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'tax_rate' => $row['tax_rate'],
                    'discount' => $row['discount'],
                    'line_total' => $row['line_total'],
                ]);
            }

            DB::commit();

            return redirect()
                ->route('pos.sales.show', $sale)
                ->with('success', 'Vente enregistrée. Ticket '.$ticketNumber);
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur lors de la vente : '.$e->getMessage());
        }
    }

    private function defaultTaxRate(Product $product): float
    {
        $vat = strtolower((string) ($product->vat_category ?? ''));
        if (str_contains($vat, '10') || str_contains($vat, 'réduit') || str_contains($vat, 'reduit')) {
            return 10.0;
        }
        if (str_contains($vat, '0') || str_contains($vat, 'exempt')) {
            return 0.0;
        }

        return 20.0;
    }
}
