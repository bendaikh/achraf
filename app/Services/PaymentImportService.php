<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentImport;
use App\Models\PaymentImportLine;
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
        protected PaymentRecordingService $recorder,
        protected PaymentMatchingService $matcher,
        protected PaymentMatchMemoryService $matchMemories
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
        $fees = $line->file_delivery_fees !== null ? round((float) $line->file_delivery_fees, 2) : null;
        $net = $line->file_net_amount !== null ? round((float) $line->file_net_amount, 2) : null;
        [$amountStatus, $diff] = $this->matcher->compareAmountsWithFees($amount, $fees, $net, $expected);
        $exclusionReason = $this->carrierExclusionReason($line->file_raw ?? [], $amount);

        $line->update([
            'invoice_id' => $invoice->id,
            'supplier_invoice_id' => null,
            'pos_sale_id' => $invoice->pos_sale_id,
            'resolved_tracking' => $line->file_tracking ?? $invoice->posSale?->primaryTrackingNumber(),
            'match_status' => PaymentImportLine::MATCH_MATCHED,
            'match_confidence' => PaymentMatchingService::CONFIDENCE_HIGH,
            'expected_amount' => $expected,
            'amount_variance' => $diff,
            'amount_status' => $amountStatus,
            'candidate_matches' => null,
            'include_in_validation' => $exclusionReason === null,
            'exclude' => $exclusionReason !== null,
            'notes' => $exclusionReason ?? 'Rattaché manuellement',
        ]);

        $this->matchMemories->rememberFromLine($line->fresh(), $invoice);

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
                        'gross_amount' => $amount,
                        'delivery_fees' => $line->file_delivery_fees !== null ? round((float) $line->file_delivery_fees, 2) : null,
                        'net_received' => $line->file_net_amount !== null ? round((float) $line->file_net_amount, 2) : null,
                        'payment_method' => $meta['payment_method'],
                        'payment_reference' => $meta['payment_reference'] ?? $line->file_reference,
                        'notes' => $this->buildImportPaymentNote($line, $import, $meta),
                        'source' => InvoicePayment::SOURCE_IMPORT,
                        'tracking_number' => $line->resolved_tracking ?? $line->file_tracking,
                        'carrier' => $this->resolveCarrierName($line),
                        'payment_import_id' => $import->id,
                        'payment_import_line_id' => $line->id,
                        'dedupe_key' => $dedupeKey,
                        'allow_overpayment' => $line->allow_overpayment,
                        'reconciliation_metadata' => $this->buildReconciliationMetadata($line, $import),
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

                    if ($payment->exists) {
                        $line->update(['supplier_invoice_payment_id' => $payment->id]);
                    }
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
        $deliveryFees = $this->parseAmount($this->pickField($row, ['frais', 'fees', 'delivery_fees', 'frais_livraison']));
        $netAmount = $this->parseAmount($this->pickField($row, ['total', 'net', 'net_encaisse', 'montant_net']));

        if ($amount === null && $netAmount !== null && $deliveryFees !== null) {
            $amount = round($netAmount + $deliveryFees, 2);
        }

        $line = PaymentImportLine::create([
            'payment_import_id' => $import->id,
            'row_number' => $lineNumber,
            'file_reference' => $reference,
            'file_tracking' => $tracking,
            'file_order_ref' => $orderRef ?? $invoiceRef,
            'file_amount' => $amount,
            'file_delivery_fees' => $deliveryFees,
            'file_net_amount' => $netAmount,
            'file_raw' => $row,
            'normalized_lookup' => mb_strtolower(trim((string) ($tracking ?? $orderRef ?? $reference ?? ''))),
            'match_status' => PaymentImportLine::MATCH_UNMATCHED,
            'include_in_validation' => true,
            'exclude' => false,
        ]);

        if ($scope === PaymentImport::SCOPE_SALES) {
            $this->matchSalesLine($line, $row, $tracking, $orderRef, $invoiceRef, $reference, $amount, $import);
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
        array $row,
        ?string $tracking,
        ?string $orderRef,
        ?string $invoiceRef,
        ?string $reference,
        ?float $amount,
        PaymentImport $import
    ): void {
        $result = $this->matcher->matchSalesLine([
            'tracking' => $tracking,
            'order_ref' => $orderRef,
            'invoice_ref' => $invoiceRef,
            'reference' => $reference,
            'external_ref' => $this->pickField($row, ['external_id', 'order_id', 'marketplace_id', 'shopify', 'jumia']),
            'client_name' => $this->pickField($row, ['client', 'nom', 'nom_client', 'destinataire', 'beneficiaire', 'customer', 'name']),
            'client_phone' => $this->pickField($row, ['telephone', 'tel', 'phone', 'mobile', 'gsm', 'whatsapp']),
            'city' => $this->pickField($row, ['ville', 'city', 'localite']),
            'delivery_date' => $this->pickField($row, ['date_de_livraison', 'delivery_date', 'date_livraison', 'date']),
            'pickup_date' => $this->pickField($row, ['date_de_ramassage', 'pickup_date', 'date_ramassage']),
            'gross_amount' => $amount,
            'delivery_fees' => $line->file_delivery_fees !== null ? (float) $line->file_delivery_fees : null,
            'net_amount' => $line->file_net_amount !== null ? (float) $line->file_net_amount : null,
            'raw' => $row,
        ]);

        $candidatePayload = $result['candidates'];

        if ($result['status'] === PaymentImportLine::MATCH_UNMATCHED) {
            $line->update([
                'match_status' => PaymentImportLine::MATCH_UNMATCHED,
                'match_confidence' => $result['confidence'],
                'match_criteria' => $result['criteria'] ?: null,
                'match_score' => $result['score'] ?: null,
                'candidate_matches' => $candidatePayload ?: null,
                'include_in_validation' => false,
                'notes' => $result['notes'],
            ]);

            return;
        }

        if ($result['status'] === PaymentImportLine::MATCH_AMBIGUOUS) {
            $line->update([
                'match_status' => PaymentImportLine::MATCH_AMBIGUOUS,
                'match_confidence' => $result['confidence'],
                'match_criteria' => $result['criteria'] ?: null,
                'match_score' => $result['score'] ?: null,
                'candidate_matches' => $candidatePayload,
                'include_in_validation' => false,
                'notes' => $result['notes'],
            ]);

            return;
        }

        $invoice = Invoice::query()->with(['items', 'payments', 'posSale.fulfillments'])->find($result['invoice_id']);
        if (! $invoice) {
            $line->update(['match_status' => PaymentImportLine::MATCH_UNMATCHED, 'include_in_validation' => false]);

            return;
        }

        if ($this->isDuplicateSalesPayment($invoice, $tracking, $reference, $amount, $import)) {
            $line->update([
                'invoice_id' => $invoice->id,
                'pos_sale_id' => $invoice->pos_sale_id,
                'resolved_tracking' => $result['resolved_tracking'],
                'match_status' => PaymentImportLine::MATCH_DUPLICATE,
                'match_confidence' => PaymentMatchingService::CONFIDENCE_HIGH,
                'match_criteria' => $result['criteria'],
                'match_score' => $result['score'],
                'exclude' => true,
                'include_in_validation' => false,
                'expected_amount' => round($invoice->remaining_balance, 2),
                'notes' => 'Paiement déjà enregistré pour cette référence/tracking',
            ]);

            return;
        }

        $expected = round($invoice->remaining_balance, 2);
        $payAmount = $amount ?? $expected;
        $fees = $line->file_delivery_fees !== null ? (float) $line->file_delivery_fees : null;
        $net = $line->file_net_amount !== null ? (float) $line->file_net_amount : null;
        [$amountStatus, $diff] = $this->matcher->compareAmountsWithFees($payAmount, $fees, $net, $expected);

        $line->update([
            'invoice_id' => $invoice->id,
            'pos_sale_id' => $invoice->pos_sale_id,
            'resolved_tracking' => $result['resolved_tracking'],
            'match_status' => PaymentImportLine::MATCH_MATCHED,
            'match_confidence' => $result['confidence'],
            'match_criteria' => $result['criteria'],
            'match_score' => $result['score'],
            'candidate_matches' => null,
            'expected_amount' => $expected,
            'amount_variance' => $diff,
            'amount_status' => $amountStatus,
            'include_in_validation' => true,
            'notes' => $result['notes'],
        ]);
    }

    protected function buildImportPaymentNote(PaymentImportLine $line, PaymentImport $import, array $meta): ?string
    {
        $parts = array_filter([
            $meta['notes'] ?? null,
        ]);

        return $parts === [] ? null : implode(' — ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildReconciliationMetadata(PaymentImportLine $line, PaymentImport $import): array
    {
        return [
            'import_file' => $import->file_name,
            'import_line' => $line->row_number,
            'match_criteria' => $line->match_criteria,
            'match_score' => $line->match_score,
            'gross_amount' => $line->file_amount !== null ? round((float) $line->file_amount, 2) : null,
            'delivery_fees' => $line->file_delivery_fees !== null ? round((float) $line->file_delivery_fees, 2) : null,
            'net_received' => $line->file_net_amount !== null ? round((float) $line->file_net_amount, 2) : null,
            'tracking' => $line->resolved_tracking ?? $line->file_tracking,
            'carrier' => $this->resolveCarrierName($line),
            'order_number' => $line->posSale?->ticket_number,
        ];
    }

    protected function resolveCarrierName(PaymentImportLine $line): ?string
    {
        $raw = $line->file_raw ?? [];

        return $this->pickField($raw, ['transporteur', 'carrier', 'marketplace', 'livreur']);
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
