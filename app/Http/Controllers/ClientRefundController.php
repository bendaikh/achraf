<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Client;
use App\Models\ClientRefund;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\ClientRefundService;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;

class ClientRefundController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected ClientRefundService $refunds
    ) {}

    public function index(Request $request)
    {
        $query = ClientRefund::with(['client', 'invoice', 'creditNote', 'posSale', 'creator']);

        $this->applyTableSearch($query, $request, [
            'refund_number',
            'client.name',
            'invoice.invoice_number',
            'creditNote.credit_note_number',
            'payment_reference',
        ]);
        $this->applyTableDateRange($query, $request, 'refund_date');
        $this->applyTableSort($query, $request, [
            'refund_date' => 'refund_date',
        ], 'refund_date', 'desc');

        if ($request->filled('source')) {
            $query->where('source', $request->string('source'));
        }

        $refunds = $this->paginateTable($query, $request);

        return view('sales.refunds.index', compact('refunds'));
    }

    public function create(Request $request)
    {
        $refundNumber = DocumentNumberService::preview('remboursement');
        $clients = Client::orderBy('name')->get(['id', 'name']);
        $invoice = $request->filled('invoice_id')
            ? Invoice::with('client', 'creditNotes')->find($request->integer('invoice_id'))
            : null;

        return view('sales.refunds.create', compact('refundNumber', 'clients', 'invoice'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'credit_note_id' => 'nullable|exists:credit_notes,id',
            'pos_sale_id' => 'nullable|exists:pos_sales,id',
            'source' => 'nullable|string|max:30',
            'refund_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'payment_file' => 'nullable|file|max:10240',
        ]);

        $this->refunds->record($validated, $request->user());

        return redirect()->route('sales.refunds.index')->with('success', 'Remboursement client enregistré avec succès.');
    }

    public function show(ClientRefund $refund)
    {
        $refund->load(['client', 'invoice.posSale', 'creditNote', 'posSale', 'creator']);

        return view('sales.refunds.show', compact('refund'));
    }

    public function destroy(ClientRefund $refund)
    {
        $this->refunds->delete($refund);

        return redirect()->route('sales.refunds.index')->with('success', 'Remboursement supprimé.');
    }
}
