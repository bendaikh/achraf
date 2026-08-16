<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Invoice;
use App\Models\PaymentImport;
use App\Models\PaymentImportLine;
use App\Services\PaymentImportService;
use App\Services\PaymentRecordingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesPaymentController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected PaymentRecordingService $recorder,
        protected PaymentImportService $importService
    ) {}

    public function index(Request $request)
    {
        $query = Invoice::query()
            ->with([
                'client',
                'posSale.fulfillments',
                'posSale.trackings',
                'items',
            ])
            ->withSum('payments as payments_sum', 'amount');

        $this->applySearch($query, $request);
        $this->applyTableDateRange($query, $request, 'invoice_date');
        $this->applyPaymentStatusFilter($query, $request, 'invoice_payments', 'invoice_id', 'invoices');
        $this->applyFulfillmentStatusFilter($query, $request);
        $this->applySourceFilter($query, $request);
        $this->applyPaymentMethodFilter($query, $request);
        $this->applyTableSort($query, $request, [
            'invoice_date' => 'invoice_date',
            'due_date' => 'due_date',
        ], 'invoice_date', 'desc');

        $invoices = $this->paginateTable($query, $request);
        $stats = $this->buildStats($request);
        $unpaidInvoices = Invoice::query()
            ->with(['client', 'posSale.fulfillments', 'items'])
            ->withSum('payments as payments_sum', 'amount')
            ->orderByDesc('invoice_date')
            ->limit(300)
            ->get()
            ->filter(fn (Invoice $inv) => $inv->remaining_balance > 0.009)
            ->values();

        return view('sales.payments.index', compact('invoices', 'stats', 'unpaidInvoices'));
    }

    public function storeManual(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'allow_overpayment' => 'sometimes|boolean',
        ]);

        $invoice = Invoice::query()->with(['items', 'posSale.fulfillments'])->findOrFail($validated['invoice_id']);

        $this->recorder->recordInvoicePayment($invoice, [
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'allow_overpayment' => (bool) ($validated['allow_overpayment'] ?? false),
            'source' => 'manual',
            'tracking_number' => $invoice->posSale?->primaryTrackingNumber(),
        ]);

        return redirect()
            ->route('sales.payments.index')
            ->with('success', 'Paiement manuel enregistré. Facture et trésorerie mises à jour.');
    }

    public function bulkForm(Request $request)
    {
        $ids = collect(explode(',', (string) $request->input('ids', '')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return redirect()->route('sales.payments.index')->with('error', 'Sélectionnez au moins une facture.');
        }

        $invoices = Invoice::query()
            ->with(['client', 'posSale.fulfillments', 'items'])
            ->withSum('payments as payments_sum', 'amount')
            ->whereIn('id', $ids)
            ->get();

        return view('sales.payments.bulk', compact('invoices'));
    }

    public function storeBulk(Request $request)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'payments' => 'required|array|min:1',
            'payments.*.invoice_id' => 'required|exists:invoices,id',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.allow_overpayment' => 'sometimes|boolean',
        ]);

        $lines = collect($validated['payments'])
            ->map(fn ($row) => [
                'invoice_id' => (int) $row['invoice_id'],
                'amount' => $row['amount'],
                'allow_overpayment' => (bool) ($row['allow_overpayment'] ?? false),
            ])
            ->all();

        $payments = $this->recorder->recordBulkInvoicePayments($lines, [
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'source' => 'bulk',
            'bulk_batch' => (string) Str::uuid(),
        ]);

        return redirect()
            ->route('sales.payments.index')
            ->with('success', count($payments).' paiement(s) enregistré(s). Trésorerie mise à jour automatiquement.');
    }

    public function importForm()
    {
        return view('sales.payments.import');
    }

    public function importStore(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $import = $this->importService->createDraftFromUpload(
            $request->file('file'),
            PaymentImport::SCOPE_SALES
        );

        return redirect()
            ->route('sales.payments.import.show', $import)
            ->with('success', 'Fichier analysé. Vérifiez les correspondances avant validation.');
    }

    public function importShow(PaymentImport $paymentImport)
    {
        abort_unless($paymentImport->scope === PaymentImport::SCOPE_SALES, 404);

        $paymentImport->load([
            'lines.invoice.client',
            'lines.posSale.fulfillments',
            'user',
            'validator',
        ]);

        $searchableInvoices = Invoice::query()
            ->with(['client', 'posSale'])
            ->latest('invoice_date')
            ->limit(300)
            ->get();

        return view('sales.payments.import-review', [
            'import' => $paymentImport,
            'searchableInvoices' => $searchableInvoices,
        ]);
    }

    public function importUpdateLine(Request $request, PaymentImport $paymentImport, PaymentImportLine $line)
    {
        abort_unless($paymentImport->scope === PaymentImport::SCOPE_SALES, 404);
        abort_unless($line->payment_import_id === $paymentImport->id, 404);
        abort_unless($paymentImport->isDraft(), 422);

        $validated = $request->validate([
            'invoice_id' => 'nullable|exists:invoices,id',
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

        if (array_key_exists('file_amount', $validated) && $validated['file_amount'] !== null) {
            $line->update(['file_amount' => $validated['file_amount']]);
            if ($line->invoice_id) {
                $this->importService->attachInvoiceToLine($line->fresh(), Invoice::findOrFail($line->invoice_id));
            }
        }

        if (! empty($validated['invoice_id'])) {
            $this->importService->attachInvoiceToLine($line, Invoice::findOrFail($validated['invoice_id']));
        }

        $this->importService->refreshCounts($paymentImport);

        return redirect()
            ->route('sales.payments.import.show', $paymentImport)
            ->with('success', 'Ligne mise à jour.');
    }

    public function importValidate(Request $request, PaymentImport $paymentImport)
    {
        abort_unless($paymentImport->scope === PaymentImport::SCOPE_SALES, 404);

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            $result = $this->importService->validateImport($paymentImport, $validated);
        } catch (ValidationException $e) {
            return redirect()
                ->route('sales.payments.import.show', $paymentImport)
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('sales.payments.index')
            ->with('success', sprintf(
                'Import validé : %d paiement(s) créé(s), %d ignoré(s). Trésorerie mise à jour.',
                $result['created'],
                $result['skipped']
            ));
    }

    protected function applySearch(Builder $query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = '%'.$request->input('search').'%';

        $query->where(function (Builder $q) use ($search) {
            $q->where('invoice_number', 'like', $search)
                ->orWhereHas('client', fn (Builder $cq) => $cq->where('name', 'like', $search))
                ->orWhereHas('posSale', fn (Builder $pq) => $pq->where('ticket_number', 'like', $search))
                ->orWhereHas('posSale.fulfillments', fn (Builder $fq) => $fq->where('tracking_number', 'like', $search));
        });
    }

    protected function applyFulfillmentStatusFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('fulfillment_status')) {
            return;
        }

        $status = $request->input('fulfillment_status');
        $query->whereHas('posSale', fn (Builder $q) => $q->where('fulfillment_status', $status));
    }

    protected function applySourceFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('source')) {
            return;
        }

        $source = $request->input('source');
        if ($source === 'manual') {
            $query->where(function (Builder $q) {
                $q->whereDoesntHave('posSale')
                    ->orWhereHas('posSale', fn (Builder $pq) => $pq->whereNull('source')->orWhere('source', 'pos'));
            });

            return;
        }

        $query->whereHas('posSale', fn (Builder $q) => $q->where('source', $source));
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
            // « open » = non soldées : impayées + partiellement payées.
            'open' => $query->whereRaw("{$paidSubquery} < {$invoicesTable}.total"),
            default => null,
        };
    }

    protected function buildStats(Request $request): array
    {
        $query = Invoice::query()->with(['items'])->withSum('payments as payments_sum', 'amount');
        $this->applySearch($query, $request);
        $this->applyTableDateRange($query, $request, 'invoice_date');
        $this->applyPaymentStatusFilter($query, $request, 'invoice_payments', 'invoice_id', 'invoices');
        $this->applyFulfillmentStatusFilter($query, $request);
        $this->applySourceFilter($query, $request);
        $this->applyPaymentMethodFilter($query, $request);

        $invoices = $query->get();

        $totalAmount = $invoices->sum(fn (Invoice $invoice) => $invoice->computed_total);
        $totalPaid = $invoices->sum(fn (Invoice $invoice) => (float) ($invoice->payments_sum ?? 0));
        $totalRemaining = $invoices->sum(fn (Invoice $invoice) => max(0, $invoice->computed_total - (float) ($invoice->payments_sum ?? 0)));

        return [
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'invoice_count' => $invoices->count(),
        ];
    }
}
