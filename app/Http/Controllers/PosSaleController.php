<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\PosSale;
use Illuminate\Http\Request;

class PosSaleController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = PosSale::with(['client', 'user'])
            ->where('status', PosSale::STATUS_COMPLETED)
            ->latest('sold_at');

        $this->applyTableSearch($query, $request, ['ticket_number', 'client.name']);
        $this->applyTableDateRange($query, $request, 'sold_at', 'date_from', 'date_to');
        $this->applyTableFilter($query, $request, 'payment_method', 'payment_method');

        $sales = $query->paginate(20)->withQueryString();
        $paymentMethods = PosSale::paymentLabels();

        return view('pos.sales.index', compact('sales', 'paymentMethods'));
    }

    public function show(PosSale $sale)
    {
        $sale->load(['items.product', 'client', 'user']);

        return view('pos.sales.show', [
            'sale' => $sale,
            'paymentMethods' => PosSale::paymentLabels(),
        ]);
    }

    public function destroy(PosSale $sale)
    {
        $sale->delete();

        return redirect()->route('pos.sales.index')->with('success', 'Vente supprimée avec succès.');
    }
}
