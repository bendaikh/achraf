<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Models\PaymentImport;
use App\Models\PaymentImportLine;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use App\Models\SupplierPayment;
use App\Services\Documents\DocumentAttachmentService;
use App\Services\PaymentImportService;
use App\Services\PaymentRecordingService;
use App\Services\SupplierAccountService;
use App\Support\CompanyInfo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchasePaymentController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf;

    public function __construct(
        protected PaymentRecordingService $recorder,
        protected PaymentImportService $importService,
        protected SupplierAccountService $accounts,
        protected DocumentAttachmentService $attachments
    ) {}

    public function index(Request $request)
    {
        $query = SupplierInvoice::query()
            ->with(['supplier'])
            ->withSum('payments as payments_sum', 'amount')
            ->withSum('creditNoteAllocations as credits_sum', 'amount');

        $this->applyTableSearch($query, $request, ['invoice_number', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'invoice_date');
        $this->applyPaymentStatusFilter($query, $request, 'supplier_invoice_payments', 'supplier_invoice_id', 'supplier_invoices');
        $this->applyPaymentMethodFilter($query, $request);
        $this->applyTableSort($query, $request, [
            'invoice_date' => 'invoice_date',
            'due_date' => 'due_date',
        ], 'invoice_date', 'desc');

        $invoices = $this->paginateTable($query, $request);
        $stats = $this->buildStats($request);

        $openInvoices = SupplierInvoice::query()
            ->with(['supplier'])
            ->withSum('payments as payments_sum', 'amount')
            ->withSum('creditNoteAllocations as credits_sum', 'amount')
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get()
            ->filter(fn (SupplierInvoice $inv) => max(0, (float) $inv->total - (float) ($inv->payments_sum ?? 0) - (float) ($inv->credits_sum ?? 0)) > 0.009)
            ->values();

        $supplierAccounts = Supplier::query()
            ->orderBy('name')
            ->get()
            ->map(function (Supplier $supplier) {
                $statement = $this->accounts->statement($supplier);
                $statement['supplier'] = $supplier;

                return $statement;
            })
            ->filter(fn (array $row) => abs($row['balance']) > 0.009
                || $row['open_invoices'] > 0.009
                || $row['total_payments'] > 0.009
                || $row['total_advances'] > 0.009)
            ->values();

        return view('purchases.payments.index', compact('invoices', 'stats', 'openInvoices', 'supplierAccounts'));
    }

    public function storeManual(Request $request)
    {
        $validated = $request->validate([
            'supplier_invoice_id' => 'required|exists:supplier_invoices,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'allow_overpayment' => 'sometimes|boolean',
        ]);

        $invoice = SupplierInvoice::findOrFail($validated['supplier_invoice_id']);

        $this->recorder->recordSupplierPayment($invoice, [
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'allow_overpayment' => true,
            'use_credits' => (bool) $request->boolean('use_credits'),
            'use_advances' => true,
            'source' => 'manual',
        ]);

        return redirect()
            ->route('purchases.payments.settle', $invoice->supplier_id)
            ->with('success', 'Règlement enregistré. Le surplus éventuel est conservé en avance fournisseur.');
    }

    public function bulkForm(Request $request)
    {
        $ids = collect(explode(',', (string) $request->input('ids', '')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return redirect()->route('purchases.payments.index')->with('error', 'Sélectionnez au moins une facture.');
        }

        $invoices = SupplierInvoice::query()
            ->with(['supplier'])
            ->withSum('payments as payments_sum', 'amount')
            ->withSum('creditNoteAllocations as credits_sum', 'amount')
            ->whereIn('id', $ids)
            ->get();

        $supplierIds = $invoices->pluck('supplier_id')->unique();
        if ($supplierIds->count() === 1) {
            return redirect()->route('purchases.payments.settle', [
                'supplier' => $supplierIds->first(),
                'invoices' => $ids->implode(','),
            ]);
        }

        return view('purchases.payments.bulk', compact('invoices'));
    }

    public function storeBulk(Request $request)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'payments' => 'required|array|min:1',
            'payments.*.supplier_invoice_id' => 'required|exists:supplier_invoices,id',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.allow_overpayment' => 'sometimes|boolean',
        ]);

        $lines = collect($validated['payments'])
            ->map(fn ($row) => [
                'supplier_invoice_id' => (int) $row['supplier_invoice_id'],
                'amount' => $row['amount'],
                'allow_overpayment' => (bool) ($row['allow_overpayment'] ?? false),
            ])
            ->all();

        $payments =         $this->recorder->recordBulkSupplierPayments($lines, [
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'source' => 'bulk',
            'bulk_batch' => (string) Str::uuid(),
            'use_credits' => (bool) $request->boolean('use_credits'),
            'use_advances' => true,
        ]);

        return redirect()
            ->route('purchases.payments.index')
            ->with('success', count($payments).' paiement(s) enregistré(s).');
    }

    public function settle(Request $request, Supplier $supplier)
    {
        $statement = $this->accounts->statement($supplier);
        $openInvoices = $this->accounts->openInvoicesPayload($supplier);
        $credits = $this->accounts->availableCreditsPayload($supplier);
        $preselected = collect(explode(',', (string) $request->input('invoices', '')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return view('purchases.payments.settle', [
            'supplier' => $supplier,
            'statement' => $statement,
            'openInvoices' => $openInvoices,
            'credits' => $credits,
            'preselected' => $preselected,
            'chequeStatuses' => SupplierInvoicePayment::CHEQUE_STATUSES,
            'paymentHistory' => $this->accounts->paymentHistory($supplier),
        ]);
    }

    public function storeSettlement(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'invoice_ids' => 'nullable|array',
            'invoice_ids.*' => 'integer|exists:supplier_invoices,id',
            'use_credits' => 'sometimes|boolean',
            'use_advances' => 'sometimes|boolean',
            'payment_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'cheque_number' => 'nullable|string|max:255',
            'cheque_bank' => 'nullable|string|max:255',
            'cheque_date' => 'nullable|date',
            'cheque_due_date' => 'nullable|date',
            'cheque_beneficiary' => 'nullable|string|max:255',
            'cheque_status' => ['nullable', 'string', Rule::in(array_keys(SupplierInvoicePayment::CHEQUE_STATUSES))],
        ]);

        if (($validated['payment_method'] ?? '') === 'Chèque') {
            $request->validate([
                'cheque_number' => 'required|string|max:255',
                'cheque_bank' => 'required|string|max:255',
                'cheque_date' => 'required|date',
                'cheque_status' => ['required', Rule::in(array_keys(SupplierInvoicePayment::CHEQUE_STATUSES))],
            ]);
        }

        $header = $this->accounts->recordSettlement($supplier, [
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'] ?? 0,
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? ($validated['cheque_number'] ?? null),
            'notes' => $validated['notes'] ?? null,
            'invoice_ids' => $validated['invoice_ids'] ?? [],
            'use_credits' => $request->boolean('use_credits'),
            'use_advances' => $request->has('use_advances') ? $request->boolean('use_advances') : true,
            'cheque_number' => $validated['cheque_number'] ?? null,
            'cheque_bank' => $validated['cheque_bank'] ?? null,
            'cheque_date' => $validated['cheque_date'] ?? null,
            'cheque_due_date' => $validated['cheque_due_date'] ?? null,
            'cheque_beneficiary' => $validated['cheque_beneficiary'] ?? $supplier->name,
            'cheque_status' => $validated['cheque_status'] ?? null,
            'source' => 'manual',
        ]);

        if ($request->hasFile('payment_file')) {
            $category = $validated['payment_method'] === 'Chèque'
                ? 'cheque_scan'
                : ($validated['payment_method'] === 'Virement bancaire' ? 'transfer_proof' : 'primary');

            $this->attachments->store('supplier-payment-headers', $header, $request->file('payment_file'), [
                'category' => $category,
                'source' => $validated['payment_method'] === 'Chèque' ? 'scan' : 'upload',
                'reference' => $header->payment_number,
                'user_id' => $request->user()?->id,
            ]);
        }

        return redirect()
            ->route('purchases.payments.settle', $supplier)
            ->with('success', 'Règlement enregistré. Factures – avoirs – paiements – avances = solde réel fournisseur.');
    }

    public function importForm()
    {
        return view('purchases.payments.import');
    }

    public function importStore(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $import = $this->importService->createDraftFromUpload(
            $request->file('file'),
            PaymentImport::SCOPE_PURCHASES
        );

        return redirect()->route('purchases.payments.import.show', $import);
    }

    public function importShow(PaymentImport $paymentImport)
    {
        abort_unless($paymentImport->scope === PaymentImport::SCOPE_PURCHASES, 404);

        $paymentImport->load(['lines.supplierInvoice.supplier', 'user', 'validator']);

        $searchableInvoices = SupplierInvoice::query()
            ->with('supplier')
            ->latest('invoice_date')
            ->limit(300)
            ->get();

        return view('purchases.payments.import-review', [
            'import' => $paymentImport,
            'searchableInvoices' => $searchableInvoices,
        ]);
    }

    public function importUpdateLine(Request $request, PaymentImport $paymentImport, PaymentImportLine $line)
    {
        abort_unless($paymentImport->scope === PaymentImport::SCOPE_PURCHASES, 404);
        abort_unless($line->payment_import_id === $paymentImport->id, 404);
        abort_unless($paymentImport->isDraft(), 422);

        $validated = $request->validate([
            'supplier_invoice_id' => 'nullable|exists:supplier_invoices,id',
            'exclude' => 'sometimes|boolean',
            'allow_overpayment' => 'sometimes|boolean',
            'file_amount' => 'nullable|numeric|min:0',
        ]);

        if (array_key_exists('exclude', $validated)) {
            $excluded = (bool) $validated['exclude'];
            $line->update([
                'exclude' => $excluded,
                'include_in_validation' => ! $excluded && $line->match_status === PaymentImportLine::MATCH_MATCHED,
            ]);
        }

        if (array_key_exists('allow_overpayment', $validated)) {
            $line->update(['allow_overpayment' => (bool) $validated['allow_overpayment']]);
        }

        if (! empty($validated['supplier_invoice_id'])) {
            $this->importService->attachSupplierInvoiceToLine(
                $line,
                SupplierInvoice::findOrFail($validated['supplier_invoice_id'])
            );
        }

        $this->importService->refreshCounts($paymentImport);

        return redirect()->route('purchases.payments.import.show', $paymentImport)->with('success', 'Ligne mise à jour.');
    }

    public function importValidate(Request $request, PaymentImport $paymentImport)
    {
        abort_unless($paymentImport->scope === PaymentImport::SCOPE_PURCHASES, 404);

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            $result = $this->importService->validateImport($paymentImport, $validated);
        } catch (ValidationException $e) {
            return redirect()->route('purchases.payments.import.show', $paymentImport)->withErrors($e->errors());
        }

        return redirect()
            ->route('purchases.payments.index')
            ->with('success', sprintf('%d paiement(s) validé(s), %d ignoré(s).', $result['created'], $result['skipped']));
    }

    public function history(Request $request)
    {
        $query = SupplierPayment::query()->with([
            'supplier',
            'user',
            'allocations.invoice',
            'creditNoteAllocations.invoice',
            'managedDocuments.currentVersion',
            'invoicePayments.managedDocuments.currentVersion',
        ]);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }
        if ($request->filled('reference')) {
            $ref = $request->input('reference');
            $query->where(function (Builder $q) use ($ref) {
                $q->where('payment_reference', 'like', '%'.$ref.'%')
                    ->orWhere('payment_number', 'like', '%'.$ref.'%')
                    ->orWhere('cheque_number', 'like', '%'.$ref.'%');
            });
        }
        $this->applyTableDateRange($query, $request, 'payment_date');

        $payments = $this->paginateTable($query->orderByDesc('payment_date')->orderByDesc('id'), $request);

        return view('purchases.payments.history', [
            'payments' => $payments,
            'rows' => collect($payments->items())->map(fn (SupplierPayment $payment) => $this->accounts->summarizeHistoryRow($payment)),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(SupplierPayment $payment)
    {
        return view('purchases.payments.show', $this->accounts->paymentDossier($payment));
    }

    public function edit(SupplierPayment $payment)
    {
        abort_if($payment->isCancelled(), 422, 'Un règlement annulé ne peut pas être modifié.');
        $supplier = $payment->supplier;
        $statement = $this->accounts->statement($supplier);
        $openInvoices = $this->accounts->openInvoicesForEdit($supplier, $payment);
        $credits = $this->accounts->availableCreditsPayload($supplier);

        return view('purchases.payments.edit', [
            'payment' => $payment,
            'supplier' => $supplier,
            'statement' => $statement,
            'openInvoices' => $openInvoices,
            'credits' => $credits,
            'chequeStatuses' => SupplierInvoicePayment::CHEQUE_STATUSES,
        ]);
    }

    public function update(Request $request, SupplierPayment $payment)
    {
        abort_if($payment->isCancelled(), 422, 'Un règlement annulé ne peut pas être modifié.');

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'invoice_ids' => 'nullable|array',
            'invoice_ids.*' => 'integer|exists:supplier_invoices,id',
            'use_credits' => 'sometimes|boolean',
            'use_advances' => 'sometimes|boolean',
            'reason' => 'nullable|string|max:500',
            'payment_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'cheque_number' => 'nullable|string|max:255',
            'cheque_bank' => 'nullable|string|max:255',
            'cheque_date' => 'nullable|date',
            'cheque_due_date' => 'nullable|date',
            'cheque_beneficiary' => 'nullable|string|max:255',
            'cheque_status' => ['nullable', 'string', Rule::in(array_keys(SupplierInvoicePayment::CHEQUE_STATUSES))],
        ]);

        $newAmount = $this->accounts->money($validated['amount'] ?? 0);
        $reallocate = abs($newAmount - $this->accounts->money($payment->amount)) > 0.009
            || collect($validated['invoice_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all()
                !== $payment->allocations->pluck('supplier_invoice_id')->merge($payment->creditNoteAllocations->pluck('supplier_invoice_id'))->unique()->sort()->values()->all();

        $this->accounts->updateSettlement($payment, [
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'] ?? $payment->amount,
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? ($validated['cheque_number'] ?? $payment->payment_reference),
            'notes' => $validated['notes'] ?? null,
            'invoice_ids' => $validated['invoice_ids'] ?? [],
            'use_credits' => $request->boolean('use_credits'),
            'use_advances' => $request->has('use_advances') ? $request->boolean('use_advances') : true,
            'cheque_number' => $validated['cheque_number'] ?? null,
            'cheque_bank' => $validated['cheque_bank'] ?? null,
            'cheque_date' => $validated['cheque_date'] ?? null,
            'cheque_due_date' => $validated['cheque_due_date'] ?? null,
            'cheque_beneficiary' => $validated['cheque_beneficiary'] ?? $payment->supplier?->name,
            'cheque_status' => $validated['cheque_status'] ?? null,
            'reason' => $validated['reason'] ?? null,
            'reallocate' => $reallocate,
        ]);

        $payment->refresh();

        if ($request->hasFile('payment_file')) {
            $existing = $payment->activeManagedDocuments()->first();
            $category = $validated['payment_method'] === 'Chèque'
                ? 'cheque_scan'
                : ($validated['payment_method'] === 'Virement bancaire' ? 'transfer_proof' : 'primary');
            $opts = [
                'category' => $category,
                'source' => 'upload',
                'reference' => $payment->payment_number,
                'user_id' => $request->user()?->id,
            ];
            if ($existing) {
                $this->attachments->replace($existing, $request->file('payment_file'), $opts);
            } else {
                $this->attachments->store('supplier-payment-headers', $payment, $request->file('payment_file'), $opts);
            }
        }

        return redirect()->route('purchases.payments.show', $payment)->with('success', 'Règlement mis à jour. Les soldes ont été recalculés si nécessaire.');
    }

    public function cancel(Request $request, SupplierPayment $payment)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|min:3|max:1000',
        ]);

        $this->accounts->cancelPayment($payment, $validated['cancellation_reason'], $request->user()?->id);

        return redirect()
            ->route('purchases.payments.show', $payment)
            ->with('success', 'Règlement annulé. Les soldes ont été recalculés. Le règlement reste dans l’historique.');
    }

    public function print(SupplierPayment $payment)
    {
        return view('purchases.payments.print', array_merge(
            $this->accounts->paymentDossier($payment),
            ['company' => CompanyInfo::all(), 'generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(SupplierPayment $payment)
    {
        $payload = array_merge(
            $this->accounts->paymentDossier($payment),
            ['company' => CompanyInfo::all(), 'generatedBy' => auth()->user()?->name]
        );
        $pdf = Pdf::loadView('purchases.payments.pdf', $payload);
        $pdf->setPaper('a4', 'portrait');
        $number = $payment->payment_number ?: ('REG-'.$payment->id);

        return $pdf->download('reglement-'.$number.'.pdf');
    }

    protected function applyPaymentMethodFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('payment_method')) {
            return;
        }

        $method = $request->input('payment_method');
        $query->whereHas('payments', fn (Builder $q) => $q->where('payment_method', $method));
    }

    protected function applyPaymentStatusFilter(
        Builder $query,
        Request $request,
        string $paymentsTable,
        string $foreignKey,
        string $invoicesTable,
    ): void {
        if (! $request->filled('payment_status')) {
            return;
        }

        $paidSubquery = "(SELECT COALESCE(SUM(amount), 0) FROM {$paymentsTable} WHERE {$foreignKey} = {$invoicesTable}.id)";
        $creditSubquery = '(SELECT COALESCE(SUM(amount), 0) FROM supplier_credit_note_allocations WHERE supplier_invoice_id = '.$invoicesTable.'.id)';
        $settled = "({$paidSubquery} + {$creditSubquery})";

        match ($request->input('payment_status')) {
            'paid' => $query->whereRaw("{$settled} >= {$invoicesTable}.total"),
            'partial' => $query->whereRaw("{$settled} > 0")
                ->whereRaw("{$settled} < {$invoicesTable}.total"),
            'unpaid' => $query->whereRaw("{$settled} = 0"),
            'open' => $query->whereRaw("{$settled} < {$invoicesTable}.total"),
            default => null,
        };
    }

    protected function buildStats(Request $request): array
    {
        $query = SupplierInvoice::query();
        $this->applyTableSearch($query, $request, ['invoice_number', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'invoice_date');
        $this->applyPaymentStatusFilter($query, $request, 'supplier_invoice_payments', 'supplier_invoice_id', 'supplier_invoices');
        $this->applyPaymentMethodFilter($query, $request);

        $invoices = $query->withSum('payments as payments_sum', 'amount')
            ->withSum('creditNoteAllocations as credits_sum', 'amount')
            ->get();

        $totalAmount = $invoices->sum(fn (SupplierInvoice $invoice) => (float) $invoice->total);
        $totalPaid = $invoices->sum(fn (SupplierInvoice $invoice) => (float) ($invoice->payments_sum ?? 0) + (float) ($invoice->credits_sum ?? 0));
        $totalRemaining = $invoices->sum(fn (SupplierInvoice $invoice) => max(0, (float) $invoice->total - (float) ($invoice->payments_sum ?? 0) - (float) ($invoice->credits_sum ?? 0)));

        return [
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'invoice_count' => $invoices->count(),
        ];
    }
}
