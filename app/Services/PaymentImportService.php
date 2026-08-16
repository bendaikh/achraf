<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\OrderFulfillment;
use App\Models\PaymentImport;
use App\Models\PaymentImportLine;
use App\Models\PosSale;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PaymentImportService
{
    public function __construct(
        protected PaymentRecordingService $recorder
    ) {}

    public function createDraftFromUpload(UploadedFile $file, string $scope = PaymentImport::SCOPE_SALES): PaymentImport
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Formats acceptés : CSV, XLSX.',
            ]);
        }

        $contents = file_get_contents($file->getRealPath());
        $hash = hash('sha256', $contents ?: '');

        $path = $file->store('payment_imports', 'local');

        $rows = $this->parseFile($file->getRealPath(), $extension);

        $import = PaymentImport::create([
            'scope' => $scope,
            'status' => PaymentImport::STATUS_DRAFT,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $extension,
            'file_hash' => $hash,
            'uploaded_by' => Auth::id(),
            'uploaded_at' => now(),
            'total_rows' => count($rows),
        ]);

        foreach ($rows as $index => $row) {
            $this->createMatchedLine($import, $index + 1, $row, $scope);
        }

        $this->refreshCounts($import);

        return $import->fresh(['lines']);
    }

    public function refreshCounts(PaymentImport $import): void
    {
        $lines = $import->lines()->get();

        $import->update([
            'total_rows' => $lines->count(),
            'matched_count' => $lines->where('match_status', PaymentImportLine::MATCH_MATCHED)->count(),
            'ambiguous_count' => $lines->where('match_status', PaymentImportLine::MATCH_AMBIGUOUS)->count(),
            'not_found_count' => $lines->where('match_status', PaymentImportLine::MATCH_UNMATCHED)->count(),
            'duplicate_count' => $lines->where('match_status', PaymentImportLine::MATCH_DUPLICATE)->count(),
        ]);
    }

    public function attachInvoiceToLine(PaymentImportLine $line, Invoice $invoice): PaymentImportLine
    {
        $invoice->load(['items', 'payments', 'posSale.fulfillments']);
        $expected = round($invoice->remaining_balance, 2);
        $amount = $line->file_amount !== null ? round((float) $line->file_amount, 2) : $expected;
        [$amountStatus, $diff] = $this->compareAmounts($amount, $expected);
        $exclusionReason = $this->carrierExclusionReason($line->file_raw ?? [], $amount);

        $line->update([
            'invoice_id' => $invoice->id,
            'supplier_invoice_id' => null,
            'pos_sale_id' => $invoice->pos_sale_id,
            'resolved_tracking' => $invoice->posSale?->primaryTrackingNumber(),
            'match_status' => PaymentImportLine::MATCH_MATCHED,
            'expected_amount' => $expected,
            'amount_variance' => $diff,
            'amount_status' => $amountStatus,
            'candidate_matches' => null,
            'include_in_validation' => $exclusionReason === null,
            'exclude' => $exclusionReason !== null,
            'notes' => $exclusionReason ?? 'Rattaché manuellement',
        ]);

        $this->refreshCounts($line->import);

        return $line->fresh(['invoice.client', 'posSale']);
    }

    public function attachSupplierInvoiceToLine(PaymentImportLine $line, SupplierInvoice $invoice): PaymentImportLine
    {
        $expected = max(0, round((float) $invoice->total - (float) $invoice->payments()->sum('amount'), 2));
        $amount = $line->file_amount !== null ? round((float) $line->file_amount, 2) : $expected;
        [$amountStatus, $diff] = $this->compareAmounts($amount, $expected);

        $line->update([
            'supplier_invoice_id' => $invoice->id,
            'invoice_id' => null,
            'pos_sale_id' => null,
            'match_status' => PaymentImportLine::MATCH_MATCHED,
            'expected_amount' => $expected,
            'amount_variance' => $diff,
            'amount_status' => $amountStatus,
            'candidate_matches' => null,
            'include_in_validation' => true,
            'exclude' => false,
            'notes' => 'Rattaché manuellement',
        ]);

        $this->refreshCounts($line->import);

        return $line->fresh(['supplierInvoice.supplier']);
    }

    /**
     * @return array{created:int, skipped:int}
     */
    public function validateImport(PaymentImport $import, array $meta): array
    {
        if (! $import->isDraft()) {
            throw ValidationException::withMessages([
                'import' => 'Cet import a déjà été validé ou annulé.',
            ]);
        }

        $import->update([
            'payment_date' => $meta['payment_date'],
            'payment_method' => $meta['payment_method'],
            'payment_reference' => $meta['payment_reference'] ?? null,
            'notes' => $meta['notes'] ?? null,
        ]);

        $lines = $import->lines()->with(['invoice.items', 'invoice.posSale.fulfillments', 'supplierInvoice'])->get();
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($import, $lines, $meta, &$created, &$skipped) {
            foreach ($lines as $line) {
                if ($line->exclude || $line->include_in_validation === false || $line->match_status === PaymentImportLine::MATCH_DUPLICATE) {
                    $skipped++;

                    continue;
                }

                if (! $line->isReadyToValidate()) {
                    throw ValidationException::withMessages([
                        'import' => "La ligne {$line->line_number} n'est pas prête (correspondance ou écart à corriger).",
                    ]);
                }

                $amount = $line->file_amount !== null
                    ? round((float) $line->file_amount, 2)
                    : round((float) $line->expected_amount, 2);

                if ($amount <= 0 || $this->carrierExclusionReason($line->file_raw ?? [], $amount) !== null) {
                    $line->update([
                        'exclude' => true,
                        'include_in_validation' => false,
                    ]);
                    $skipped++;

                    continue;
                }

                if ($import->scope === PaymentImport::SCOPE_SALES) {
                    $invoice = $line->invoice;
                    if (! $invoice) {
                        throw ValidationException::withMessages([
                            'import' => "Ligne {$line->line_number} : facture manquante.",
                        ]);
                    }

                    $dedupeKey = $this->recorder->buildDedupeKey([
                        'scope' => 'sales',
                        'invoice_id' => $invoice->id,
                        'reference' => $meta['payment_reference'] ?? $line->file_reference,
                        'tracking' => $line->resolved_tracking ?? $line->file_tracking,
                        'import_line_id' => $line->id,
                        'file_hash' => $import->file_hash,
                        'amount' => $amount,
                        'source' => 'import',
                    ]);

                    if ($dedupeKey && InvoicePayment::query()->where('dedupe_key', $dedupeKey)->exists()) {
                        $line->update(['match_status' => PaymentImportLine::MATCH_DUPLICATE, 'exclude' => true]);
                        $skipped++;

                        continue;
                    }

                    $payment = $this->recorder->recordInvoicePayment($invoice, [
                        'payment_date' => $meta['payment_date'],
                        'amount' => $amount,
                        'payment_method' => $meta['payment_method'],
                        'payment_reference' => $meta['payment_reference'] ?? $line->file_reference,
                        'notes' => $meta['notes'] ?? null,
                        'source' => InvoicePayment::SOURCE_IMPORT,
                        'tracking_number' => $line->resolved_tracking ?? $line->file_tracking,
                        'payment_import_id' => $import->id,
                        'payment_import_line_id' => $line->id,
                        'dedupe_key' => $dedupeKey,
                        'allow_overpayment' => $line->allow_overpayment,
                    ]);

                    $line->update(['invoice_payment_id' => $payment->id]);
                } else {
                    $invoice = $line->supplierInvoice;
                    if (! $invoice) {
                        throw ValidationException::withMessages([
                            'import' => "Ligne {$line->line_number} : facture fournisseur manquante.",
                        ]);
                    }

                    $dedupeKey = $this->recorder->buildDedupeKey([
                        'scope' => 'purchases',
                        'invoice_id' => $invoice->id,
                        'reference' => $meta['payment_reference'] ?? $line->file_reference,
                        'import_line_id' => $line->id,
                        'file_hash' => $import->file_hash,
                        'amount' => $amount,
                        'source' => 'import',
                    ]);

                    if ($dedupeKey && SupplierInvoicePayment::query()->where('dedupe_key', $dedupeKey)->exists()) {
                        $line->update(['match_status' => PaymentImportLine::MATCH_DUPLICATE, 'exclude' => true]);
                        $skipped++;

                        continue;
                    }

                    $payment = $this->recorder->recordSupplierPayment($invoice, [
                        'payment_date' => $meta['payment_date'],
                        'amount' => $amount,
                        'payment_method' => $meta['payment_method'],
                        'payment_reference' => $meta['payment_reference'] ?? $line->file_reference,
                        'notes' => $meta['notes'] ?? null,
                        'source' => SupplierInvoicePayment::SOURCE_IMPORT,
                        'payment_import_id' => $import->id,
                        'payment_import_line_id' => $line->id,
                        'dedupe_key' => $dedupeKey,
                        'allow_overpayment' => $line->allow_overpayment,
                    ]);

                    $line->update(['supplier_invoice_payment_id' => $payment->id]);
                }

                $created++;
            }

            $import->update([
                'status' => PaymentImport::STATUS_VALIDATED,
                'validated_at' => now(),
                'validated_by' => Auth::id(),
            ]);
        });

        $this->refreshCounts($import->fresh());

        return ['created' => $created, 'skipped' => $skipped];
    }

    protected function createMatchedLine(PaymentImport $import, int $lineNumber, array $row, string $scope): PaymentImportLine
    {
        $tracking = $this->pickField($row, [
            'code_d_envoi', 'code_envoi', 'code d’envoi', 'code d\'envoi',
            'tracking', 'tracking_number', 'n_suivi', 'numero_suivi', 'suivi',
            'tracking number', 'n° suivi', 'no_suivi',
        ]);
        $orderRef = $this->pickField($row, ['order', 'commande', 'order_number', 'n_commande', 'numero_commande', 'shopify', 'reference_commande']);
        $invoiceRef = $this->pickField($row, ['invoice', 'facture', 'invoice_number', 'n_facture', 'numero_facture']);
        $reference = $this->pickField($row, ['reference', 'ref', 'libelle', 'label', 'description', 'motif'])
            ?? $tracking
            ?? $orderRef
            ?? $invoiceRef;
        // CRBT is the gross customer payment. "Total" is the carrier's net
        // remittance after fees and must not leave the invoice partially paid.
        $amount = $this->extractPaymentAmount($row);

        $line = PaymentImportLine::create([
            'payment_import_id' => $import->id,
            'row_number' => $lineNumber,
            'file_reference' => $reference,
            'file_tracking' => $tracking,
            'file_order_ref' => $orderRef ?? $invoiceRef,
            'file_amount' => $amount,
            'file_raw' => $row,
            'normalized_lookup' => mb_strtolower(trim((string) ($tracking ?? $orderRef ?? $reference ?? ''))),
            'match_status' => PaymentImportLine::MATCH_UNMATCHED,
            'include_in_validation' => true,
            'exclude' => false,
        ]);

        if ($scope === PaymentImport::SCOPE_SALES) {
            $this->matchSalesLine($line, $tracking, $orderRef, $invoiceRef, $reference, $amount, $import);
        } else {
            $this->matchPurchaseLine($line, $invoiceRef, $reference, $amount, $import);
        }

        if ($reason = $this->carrierExclusionReason($row, $amount)) {
            $line->update([
                'exclude' => true,
                'include_in_validation' => false,
                'notes' => $reason,
            ]);
        }

        return $line->fresh();
    }

    protected function carrierExclusionReason(array $row, ?float $amount): ?string
    {
        $status = $this->pickField($row, ['status', 'statut']);
        if ($status !== null) {
            $normalizedStatus = Str::of(Str::ascii($status))
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->toString();

            if (in_array($normalizedStatus, ['rembourse', 'reimbursed', 'retourne', 'returned'], true)) {
                return 'Ligne transporteur remboursée/retournée — aucun paiement client à créer';
            }

            if (! in_array($normalizedStatus, ['livre', 'delivered'], true)) {
                return "Statut transporteur « {$status} » non éligible au règlement";
            }
        }

        if ($amount !== null && $amount <= 0) {
            return 'Montant CRBT nul ou négatif — ligne conservée pour contrôle uniquement';
        }

        return null;
    }

    protected function extractPaymentAmount(array $row): ?float
    {
        $amount = $this->parseAmount($this->pickField($row, ['crbt', 'contre_remboursement']));

        return $amount ?? $this->parseAmount($this->pickField($row, [
            'amount', 'montant', 'montant_ttc', 'solde', 'paiement',
            'encaisse', 'règlement', 'reglement', 'total',
        ]));
    }

    protected function matchSalesLine(
        PaymentImportLine $line,
        ?string $tracking,
        ?string $orderRef,
        ?string $invoiceRef,
        ?string $reference,
        ?float $amount,
        PaymentImport $import
    ): void {
        $candidates = collect();

        if ($tracking) {
            $saleIds = OrderFulfillment::query()
                ->where('tracking_number', $tracking)
                ->pluck('pos_sale_id');

            if ($saleIds->isNotEmpty()) {
                $candidates = $candidates->merge(
                    Invoice::query()->whereIn('pos_sale_id', $saleIds)->pluck('id')
                );
            }
        }

        foreach ([$orderRef, $reference, $tracking] as $token) {
            if (! $token) {
                continue;
            }
            foreach ($this->orderNumberVariants($token) as $variant) {
                $saleIds = PosSale::query()
                    ->where('ticket_number', 'like', '%'.$variant.'%')
                    ->pluck('id');
                if ($saleIds->isNotEmpty()) {
                    $candidates = $candidates->merge(
                        Invoice::query()->whereIn('pos_sale_id', $saleIds)->pluck('id')
                    );
                }
            }
        }

        if ($invoiceRef) {
            $candidates = $candidates->merge(
                Invoice::query()->where('invoice_number', 'like', '%'.$invoiceRef.'%')->pluck('id')
            );
        }

        $candidateIds = $candidates->unique()->values()->all();

        if ($candidateIds === []) {
            $line->update([
                'match_status' => PaymentImportLine::MATCH_UNMATCHED,
                'include_in_validation' => false,
                'notes' => 'Aucune commande/facture reconnue',
            ]);

            return;
        }

        if (count($candidateIds) > 1) {
            $line->update([
                'match_status' => PaymentImportLine::MATCH_AMBIGUOUS,
                'candidate_matches' => $candidateIds,
                'include_in_validation' => false,
                'notes' => count($candidateIds).' correspondances possibles',
            ]);

            return;
        }

        $invoice = Invoice::query()->with(['items', 'payments', 'posSale.fulfillments'])->find($candidateIds[0]);
        if (! $invoice) {
            $line->update(['match_status' => PaymentImportLine::MATCH_UNMATCHED, 'include_in_validation' => false]);

            return;
        }

        // Anti-doublon: same tracking/reference already paid via prior import of same file hash or same line identity
        if ($this->isDuplicateSalesPayment($invoice, $tracking, $reference, $amount, $import)) {
            $line->update([
                'invoice_id' => $invoice->id,
                'pos_sale_id' => $invoice->pos_sale_id,
                'resolved_tracking' => $tracking ?? $invoice->posSale?->primaryTrackingNumber(),
                'match_status' => PaymentImportLine::MATCH_DUPLICATE,
                'exclude' => true,
                'include_in_validation' => false,
                'expected_amount' => round($invoice->remaining_balance, 2),
                'notes' => 'Paiement déjà enregistré pour cette référence/tracking',
            ]);

            return;
        }

        $expected = round($invoice->remaining_balance, 2);
        $payAmount = $amount ?? $expected;
        [$amountStatus, $diff] = $this->compareAmounts($payAmount, $expected);

        $line->update([
            'invoice_id' => $invoice->id,
            'pos_sale_id' => $invoice->pos_sale_id,
            'resolved_tracking' => $tracking ?? $invoice->posSale?->primaryTrackingNumber(),
            'match_status' => PaymentImportLine::MATCH_MATCHED,
            'expected_amount' => $expected,
            'amount_variance' => $diff,
            'amount_status' => $amountStatus,
            'include_in_validation' => true,
            'notes' => 'Correspondance automatique',
        ]);
    }

    protected function matchPurchaseLine(
        PaymentImportLine $line,
        ?string $invoiceRef,
        ?string $reference,
        ?float $amount,
        PaymentImport $import
    ): void {
        $candidates = collect();

        foreach ([$invoiceRef, $reference] as $token) {
            if (! $token) {
                continue;
            }
            $candidates = $candidates->merge(
                SupplierInvoice::query()->where('invoice_number', 'like', '%'.$token.'%')->pluck('id')
            );
        }

        $candidateIds = $candidates->unique()->values()->all();

        if ($candidateIds === []) {
            $line->update([
                'match_status' => PaymentImportLine::MATCH_UNMATCHED,
                'include_in_validation' => false,
                'notes' => 'Aucune facture fournisseur reconnue',
            ]);

            return;
        }

        if (count($candidateIds) > 1) {
            $line->update([
                'match_status' => PaymentImportLine::MATCH_AMBIGUOUS,
                'candidate_matches' => $candidateIds,
                'include_in_validation' => false,
                'notes' => count($candidateIds).' correspondances possibles',
            ]);

            return;
        }

        $invoice = SupplierInvoice::query()->with('payments')->find($candidateIds[0]);
        $expected = max(0, round((float) $invoice->total - (float) $invoice->payments->sum('amount'), 2));
        $payAmount = $amount ?? $expected;
        [$amountStatus, $diff] = $this->compareAmounts($payAmount, $expected);

        if ($this->isDuplicatePurchasePayment($invoice, $reference, $amount, $import)) {
            $line->update([
                'supplier_invoice_id' => $invoice->id,
                'match_status' => PaymentImportLine::MATCH_DUPLICATE,
                'exclude' => true,
                'include_in_validation' => false,
                'expected_amount' => $expected,
                'notes' => 'Paiement déjà enregistré pour cette référence',
            ]);

            return;
        }

        $line->update([
            'supplier_invoice_id' => $invoice->id,
            'match_status' => PaymentImportLine::MATCH_MATCHED,
            'expected_amount' => $expected,
            'amount_variance' => $diff,
            'amount_status' => $amountStatus,
            'include_in_validation' => true,
            'notes' => 'Correspondance automatique',
        ]);
    }

    protected function isDuplicateSalesPayment(
        Invoice $invoice,
        ?string $tracking,
        ?string $reference,
        ?float $amount,
        PaymentImport $import
    ): bool {
        $query = InvoicePayment::query()->where('invoice_id', $invoice->id);

        $query->where(function ($q) use ($tracking, $reference, $import, $amount) {
            if ($tracking) {
                $q->orWhere('tracking_number', $tracking);
            }
            if ($reference) {
                $q->orWhere('payment_reference', $reference);
            }
            if ($import->file_hash) {
                $q->orWhereHas('paymentImport', fn ($iq) => $iq->where('file_hash', $import->file_hash));
            }
            if ($amount !== null) {
                // soft signal only when combined with tracking/ref above
            }
        });

        if (! $tracking && ! $reference && ! $import->file_hash) {
            return false;
        }

        return $query->exists();
    }

    protected function isDuplicatePurchasePayment(
        SupplierInvoice $invoice,
        ?string $reference,
        ?float $amount,
        PaymentImport $import
    ): bool {
        if (! $reference && ! $import->file_hash) {
            return false;
        }

        return SupplierInvoicePayment::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where(function ($q) use ($reference, $import) {
                if ($reference) {
                    $q->orWhere('payment_reference', $reference);
                }
                if ($import->file_hash) {
                    $q->orWhereHas('paymentImport', fn ($iq) => $iq->where('file_hash', $import->file_hash));
                }
            })
            ->exists();
    }

    /**
     * @return array{0:string,1:float}
     */
    protected function compareAmounts(float $fileAmount, float $expected): array
    {
        $diff = round($fileAmount - $expected, 2);

        if (abs($diff) < 0.01) {
            return [PaymentImportLine::AMOUNT_OK, 0.0];
        }

        if ($diff > 0) {
            return [PaymentImportLine::AMOUNT_OVERPAYMENT, $diff];
        }

        return [PaymentImportLine::AMOUNT_DISCREPANCY, $diff];
    }

    /**
     * @return list<string>
     */
    public function orderNumberVariants(string $value): array
    {
        $value = trim($value);
        $variants = [$value, ltrim($value, '#')];

        // EGRFTC11470 → FTC11470
        if (preg_match('/([A-Z]{2,}\d{3,})/i', $value, $m)) {
            $variants[] = strtoupper($m[1]);
        }

        // Strip common carrier prefixes (letters before known order pattern)
        if (preg_match('/(?:EGR|COL|EXP|CHR)?([A-Z]*\d+)/i', $value, $m)) {
            $variants[] = strtoupper($m[1]);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseFile(string $path, string $extension): array
    {
        if ($extension === 'csv') {
            return $this->parseCsv($path);
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        return $this->rowsToAssociative($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return [];
        }

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        return $this->rowsToAssociative($rows);
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function rowsToAssociative(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $rows[0]);

        $out = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if ($this->rowIsEmpty($row)) {
                continue;
            }
            $assoc = [];
            foreach ($header as $idx => $key) {
                if ($key === '') {
                    continue;
                }
                $assoc[$key] = $row[$idx] ?? null;
            }
            $out[] = $assoc;
        }

        return $out;
    }

    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function pickField(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader((string) $key);

            foreach ($row as $rowKey => $value) {
                if ($rowKey === $normalized || str_contains((string) $rowKey, $normalized)) {
                    $value = trim((string) $value);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header)) ?? trim($header);

        return Str::of(Str::ascii($header))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    protected function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $raw = trim((string) $value);
        $raw = str_replace([' ', 'DH', 'Mad', 'MAD', '€'], '', $raw);
        $raw = str_replace(',', '.', $raw);
        $raw = preg_replace('/[^0-9.\-]/', '', $raw);

        return is_numeric($raw) ? round((float) $raw, 2) : null;
    }
}
