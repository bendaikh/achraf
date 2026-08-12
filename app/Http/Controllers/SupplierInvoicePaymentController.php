<?php

namespace App\Http\Controllers;

use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use App\Services\PaymentRecordingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupplierInvoicePaymentController extends Controller
{
    public function __construct(
        protected PaymentRecordingService $recorder
    ) {}

    public function index(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load(['supplier', 'payments.user', 'payments.paymentImport']);

        return view('purchases.supplier-invoices.payments.index', compact('supplierInvoice'));
    }

    public function store(Request $request, SupplierInvoice $supplierInvoice)
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
            $filePath = $request->file('payment_file')->store('supplier_invoice_payments', 'public');
        }

        $this->recorder->recordSupplierPayment($supplierInvoice, [
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'payment_file_path' => $filePath,
            'notes' => $validated['notes'] ?? null,
            'allow_overpayment' => (bool) ($validated['allow_overpayment'] ?? false),
            'source' => 'manual',
        ]);

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
