<?php

namespace App\Http\Controllers;

use App\Models\CustomerReturn;
use App\Models\PosSale;
use App\Models\Warehouse;
use App\Services\CustomerReturnService;
use App\Support\StockSettings;
use Illuminate\Http\Request;

class CustomerReturnController extends Controller
{
    public function __construct(
        protected CustomerReturnService $returns
    ) {}

    public function index(Request $request)
    {
        $enabled = StockSettings::customerReturnsEnabled();

        $returns = CustomerReturn::query()
            ->with(['client', 'order', 'invoice', 'creditNote'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->input('search'));
                $q->where(function ($inner) use ($s) {
                    $inner->where('reference', 'like', '%'.$s.'%')
                        ->orWhere('tracking_number', 'like', '%'.$s.'%')
                        ->orWhereHas('order', fn ($o) => $o->where('ticket_number', 'like', '%'.$s.'%'));
                });
            })
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('sales.returns.index', compact('returns', 'enabled'));
    }

    public function scan(Request $request)
    {
        $this->ensureEnabled();

        $query = trim((string) $request->input('q', ''));
        $matches = $query !== '' ? $this->returns->searchOrders($query) : [];

        return view('sales.returns.scan', [
            'query' => $query,
            'matches' => $matches,
            'enabled' => true,
        ]);
    }

    public function start(Request $request)
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'pos_sale_id' => 'required|exists:pos_sales,id',
            'search_key' => 'nullable|string|max:255',
        ]);

        $order = PosSale::query()->findOrFail($validated['pos_sale_id']);
        $return = $this->returns->createDraft($order, $validated['search_key'] ?? null);

        return redirect()->route('sales.returns.show', $return);
    }

    public function show(CustomerReturn $customerReturn)
    {
        $this->ensureEnabled();

        $customerReturn->load([
            'lines.product',
            'lines.variant',
            'lines.warehouse',
            'lines.location',
            'order.items.product',
            'order.items.variant',
            'invoice',
            'creditNote',
            'client',
        ]);

        $context = $this->returns->orderContext($customerReturn->order);
        $warehouses = Warehouse::query()->active()->physical()->orderBy('name')->get();
        $sav = Warehouse::savWarehouse();

        return view('sales.returns.show', [
            'return' => $customerReturn,
            'context' => $context,
            'warehouses' => $warehouses,
            'sav' => $sav,
            'conditions' => \App\Models\CustomerReturnLine::CONDITIONS,
        ]);
    }

    public function update(Request $request, CustomerReturn $customerReturn)
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
            'create_credit_note' => 'nullable|boolean',
            'lines' => 'required|array',
            'lines.*.quantity_received' => 'nullable|integer|min:0',
            'lines.*.condition' => 'nullable|string',
            'lines.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'lines.*.warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
            'lines.*.notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->returns->updateDraft(
                $customerReturn,
                $validated['lines'],
                $validated['notes'] ?? null,
                $request->boolean('create_credit_note', true)
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Brouillon de retour enregistré. Aucun stock ni avoir créé tant que vous n’avez pas validé.');
    }

    public function validateReturn(CustomerReturn $customerReturn)
    {
        $this->ensureEnabled();

        try {
            $validated = $this->returns->validate($customerReturn);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = 'Retour '.$validated->reference.' validé.';
        if ($validated->credit_note_id) {
            $msg .= ' Avoir créé.';
        } elseif ($validated->create_credit_note && ! $validated->invoice_id) {
            $msg .= ' Pas de facture — aucun avoir généré.';
        }

        return redirect()->route('sales.returns.show', $validated)->with('success', $msg);
    }

    public function cancel(CustomerReturn $customerReturn)
    {
        $this->ensureEnabled();

        try {
            $this->returns->cancelDraft($customerReturn);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.returns.index')->with('success', 'Brouillon annulé (aucun mouvement de stock).');
    }

    protected function ensureEnabled(): void
    {
        if (! StockSettings::customerReturnsEnabled()) {
            abort(403, 'Retours clients / Scan retours désactivés.');
        }
    }
}
