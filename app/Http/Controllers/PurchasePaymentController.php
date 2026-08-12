<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\PaymentImport;
use App\Models\PaymentImportLine;
use App\Models\SupplierInvoice;
use App\Services\PaymentImportService;
use App\Services\PaymentRecordingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchasePaymentController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected PaymentRecordingService $recorder,
        protected PaymentImportService $importService
    ) {}

    public function index(Request $request)
    {
        $query = SupplierInvoice::query()
            ->with(['supplier'])
            ->withSum('payments as payments_sum', 'amount');

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
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get()
            ->filter(fn (SupplierInvoice $inv) => max(0, (float) $inv->total - (float) ($inv->payments_sum ?? 0)) > 0.009)
            ->values();

        return view('purchases.payments.index', compact('invoices', 'stats', 'openInvoices'));
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
            'allow_overpayment' => (bool) ($validated['allow_overpayment'] ?? false),
            'source' => 'manual',
        ]);

        return redirect()
            ->route('purchases.payments.index')
            ->with('success', 'Paiement manuel enregistré. Trésorerie mise à jour.');
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
            ->whereIn('id', $ids)
            ->get();

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

        $payments = $this->recorder->recordBulkSupplierPayments($lines, [
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'source' => 'bulk',
            'bulk_batch' => (string) Str::uuid(),
        ]);

        return redirect()
            ->route('purchases.payments.index')
            ->with('success', count($payments).' paiement(s) enregistré(s).');
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

        match ($request->input('payment_status')) {
            'paid' => $query->whereRaw("{$paidSubquery} >= {$invoicesTable}.total"),
            'partial' => $query->whereRaw("{$paidSubquery} > 0")
                ->whereRaw("{$paidSubquery} < {$invoicesTable}.total"),
            'unpaid' => $query->whereRaw("{$paidSubquery} = 0"),
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

        $invoices = $query->withSum('payments as payments_sum', 'amount')->get();

        $totalAmount = $invoices->sum(fn (SupplierInvoice $invoice) => (float) $invoice->total);
        $totalPaid = $invoices->sum(fn (SupplierInvoice $invoice) => (float) ($invoice->payments_sum ?? 0));
        $totalRemaining = $invoices->sum(fn (SupplierInvoice $invoice) => max(0, (float) $invoice->total - (float) ($invoice->payments_sum ?? 0)));

        return [
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'invoice_count' => $invoices->count(),
        ];
    }
}
