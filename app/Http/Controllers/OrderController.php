<?php

namespace App\Http\Controllers;

use App\Models\PosSale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = PosSale::with(['client', 'user', 'items'])
            ->orderBy('sold_at', 'desc');

        // Filter by source (Shopify, POS, etc.)
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by ticket number, client name, or external ID
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('external_id', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Date filters
        if ($request->filled('date_from')) {
            $query->whereDate('sold_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sold_at', '<=', $request->input('date_to'));
        }

        $orders = $query->paginate(20);

        // Calculate totals
        $totalOrders = PosSale::count();
        $totalShopifyOrders = PosSale::where('source', 'shopify')->count();
        $totalPosOrders = PosSale::whereNull('source')->orWhere('source', '!=', 'shopify')->count();
        $totalRevenue = PosSale::where('status', PosSale::STATUS_COMPLETED)->sum('total');

        return view('sales.orders.index', compact(
            'orders',
            'totalOrders',
            'totalShopifyOrders',
            'totalPosOrders',
            'totalRevenue'
        ));
    }

    public function show(PosSale $order): View
    {
        $order->load(['client', 'user', 'items.product']);

        return view('sales.orders.show', compact('order'));
    }
}
