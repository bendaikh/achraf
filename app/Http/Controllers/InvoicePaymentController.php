<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoicePaymentController extends Controller
{
    public function index(Invoice $invoice)
    {
        $invoice->load(['client', 'payments']);

        return view('sales.invoices.payments.index', compact('invoice'));
    }

    public function store(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string',
            'payment_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notes' => 'nullable|string',
        ]);

        if ($request->hasFile('payment_file')) {
            $validated['payment_file_path'] = $request->file('payment_file')->store('invoice_payments', 'public');
        }

        $invoice->payments()->create($validated);
        $invoice->syncPaymentStatus();

        return redirect()->route('invoices.payments.index', $invoice)->with('success', 'Paiement ajouté avec succès!');
    }

    public function destroy(Invoice $invoice, InvoicePayment $payment)
    {
        if ($payment->payment_file_path) {
            Storage::disk('public')->delete($payment->payment_file_path);
        }

        $payment->delete();
        $invoice->syncPaymentStatus();

        return redirect()->route('invoices.payments.index', $invoice)->with('success', 'Paiement supprimé avec succès!');
    }
}
