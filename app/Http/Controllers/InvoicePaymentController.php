<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\PaymentRecordingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoicePaymentController extends Controller
{
    public function __construct(
        protected PaymentRecordingService $recorder
    ) {}

    public function index(Invoice $invoice)
    {
        $invoice->load([
            'client',
            'posSale.fulfillments',
            'payments.user',
            'payments.paymentImport',
            'items',
        ]);

        return view('sales.invoices.payments.index', compact('invoice'));
    }

    public function store(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string',
            'payment_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notes' => 'nullable|string',
            'allow_overpayment' => 'sometimes|boolean',
        ]);

        $filePath = null;
        if ($request->hasFile('payment_file')) {
            $filePath = $request->file('payment_file')->store('invoice_payments', 'public');
        }

        $invoice->load(['items', 'posSale.fulfillments']);

        $this->recorder->recordInvoicePayment($invoice, [
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'payment_file_path' => $filePath,
            'notes' => $validated['notes'] ?? null,
            'allow_overpayment' => (bool) ($validated['allow_overpayment'] ?? false),
            'source' => 'manual',
            'tracking_number' => $invoice->posSale?->primaryTrackingNumber(),
        ]);

        return redirect()->route('invoices.payments.index', $invoice)->with('success', 'Paiement ajouté avec succès!');
    }

    public function destroy(Invoice $invoice, InvoicePayment $payment)
    {
        if ($payment->payment_file_path) {
            Storage::disk('public')->delete($payment->payment_file_path);
        }

        $payment->delete();
        $invoice->load('items');
        $invoice->syncPaymentStatus();

        return redirect()->route('invoices.payments.index', $invoice)->with('success', 'Paiement supprimé avec succès!');
    }
}
