<?php

namespace App\Http\Controllers;

use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use App\Services\Documents\DocumentAttachmentService;
use App\Services\PaymentRecordingService;
use App\Services\SupplierAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierInvoicePaymentController extends Controller
{
    public function __construct(
        protected PaymentRecordingService $recorder,
        protected DocumentAttachmentService $attachments,
        protected SupplierAccountService $accounts
    ) {}

    public function index(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load(['supplier', 'payments.user', 'payments.paymentImport', 'payments.managedDocuments.currentVersion', 'creditNoteAllocations.creditNote']);

        return view('purchases.supplier-invoices.payments.index', [
            'supplierInvoice' => $supplierInvoice,
            'trace' => $this->accounts->invoiceTrace($supplierInvoice),
            'chequeStatuses' => SupplierInvoicePayment::CHEQUE_STATUSES,
        ]);
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
            'cheque_number' => 'nullable|string|max:255',
            'cheque_bank' => 'nullable|string|max:255',
            'cheque_date' => 'nullable|date',
            'cheque_due_date' => 'nullable|date',
            'cheque_beneficiary' => 'nullable|string|max:255',
            'cheque_status' => ['nullable', 'string', Rule::in(array_keys(SupplierInvoicePayment::CHEQUE_STATUSES))],
        ]);

        if ($validated['payment_method'] === 'Chèque') {
            $request->validate([
                'cheque_number' => 'required|string|max:255',
                'cheque_bank' => 'required|string|max:255',
                'cheque_date' => 'required|date',
                'cheque_status' => ['required', Rule::in(array_keys(SupplierInvoicePayment::CHEQUE_STATUSES))],
            ]);
        }

        $payment = $this->recorder->recordSupplierPayment($supplierInvoice, [
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? ($validated['cheque_number'] ?? null),
            'cheque_number' => $validated['cheque_number'] ?? null,
            'cheque_bank' => $validated['cheque_bank'] ?? null,
            'cheque_date' => $validated['cheque_date'] ?? null,
            'cheque_due_date' => $validated['cheque_due_date'] ?? null,
            'cheque_beneficiary' => $validated['cheque_beneficiary'] ?? ($supplierInvoice->supplier?->name),
            'cheque_status' => $validated['cheque_status'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'allow_overpayment' => true,
            'use_credits' => $request->boolean('use_credits'),
            'use_advances' => true,
            'source' => 'manual',
        ]);

        if ($request->hasFile('payment_file') && $payment->supplierPayment) {
            $header = $payment->supplierPayment;
            $category = $validated['payment_method'] === 'Chèque'
                ? 'cheque_scan'
                : ($validated['payment_method'] === 'Virement bancaire' ? 'transfer_proof' : 'primary');

            $this->attachments->store('supplier-payment-headers', $header, $request->file('payment_file'), [
                'category' => $category,
                'source' => $validated['payment_method'] === 'Chèque' ? 'scan' : 'upload',
                'reference' => $header->payment_number,
                'user_id' => $request->user()?->id,
            ]);
        } elseif ($request->hasFile('payment_file') && $payment->exists) {
            $category = $validated['payment_method'] === 'Chèque'
                ? 'cheque_scan'
                : ($validated['payment_method'] === 'Virement bancaire' ? 'transfer_proof' : 'primary');

            $this->attachments->store('supplier-payments', $payment, $request->file('payment_file'), [
                'category' => $category,
                'source' => $validated['payment_method'] === 'Chèque' ? 'scan' : 'upload',
                'reference' => $validated['payment_method'] === 'Chèque' && ! empty($validated['cheque_number'])
                    ? 'CHQ-'.$validated['cheque_number']
                    : null,
                'user_id' => $request->user()?->id,
            ]);
        }

        return redirect()->route('supplier-invoices.payments.index', $supplierInvoice)->with('success', 'Paiement ajouté avec succès!');
    }

    public function destroy(SupplierInvoice $supplierInvoice, SupplierInvoicePayment $payment)
    {
        if ($payment->supplier_payment_id) {
            $header = $payment->supplierPayment;
            if ($header && ! $header->isCancelled()) {
                $this->accounts->cancelPayment($header, 'Annulation depuis la fiche facture');
            } elseif (! $header) {
                $payment->delete();
            }
        } else {
            if ($payment->payment_file_path) {
                Storage::disk('public')->delete($payment->payment_file_path);
            }
            $payment->delete();
        }

        return redirect()->route('supplier-invoices.payments.index', $supplierInvoice)->with('success', 'Règlement annulé. Les soldes ont été recalculés. Le règlement reste consultable dans l’historique.');
    }
}
