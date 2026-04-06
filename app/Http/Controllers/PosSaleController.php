<?php

namespace App\Http\Controllers;

use App\Models\PosSale;
use Illuminate\Http\Request;

class PosSaleController extends Controller
{
    public function index(Request $request)
    {
        $query = PosSale::with(['client', 'user'])
            ->where('status', PosSale::STATUS_COMPLETED)
            ->latest('sold_at');

        if ($request->filled('from')) {
            $query->whereDate('sold_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('sold_at', '<=', $request->date('to'));
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->string('payment_method'));
        }

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
}
