<?php

namespace App\Http\Controllers;

use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupplierInvoicePaymentController extends Controller
{
    public function index(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load(['supplier', 'payments']);
        return view('purchases.supplier-invoices.payments.index', compact('supplierInvoice'));
    }

    public function store(Request $request, SupplierInvoice $supplierInvoice)
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
            $validated['payment_file_path'] = $request->file('payment_file')->store('supplier_invoice_payments', 'public');
        }

        $supplierInvoice->payments()->create($validated);

        return redirect()->route('supplier-invoices.payments.index', $supplierInvoice)->with('success', 'Paiement ajouté avec succès!');
    }

    public function destroy(SupplierInvoice $supplierInvoice, SupplierInvoicePayment $payment)
    {
        if ($payment->payment_file_path) {
            Storage::disk('public')->delete($payment->payment_file_path);
        }
        $payment->delete();
        return redirect()->route('supplier-invoices.payments.index', $supplierInvoice)->with('success', 'Paiement supprimé avec succès!');
    }
}
