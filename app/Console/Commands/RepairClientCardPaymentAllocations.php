<?php

namespace App\Console\Commands;

use App\Services\ClientCardPaymentAllocationRepairService;
use Illuminate\Console\Command;

class RepairClientCardPaymentAllocations extends Command
{
    protected $signature = 'invoices:repair-card-payment-allocations
                            {--dry-run : Simuler sans écrire (défaut si --apply absent)}
                            {--apply : Appliquer les rattachements en base}
                            {--invoice= : Limiter à une facture (id ou numéro)}';

    protected $description = 'Rattache les paiements carte déjà enregistrés non affectés aux factures clients (dry-run par défaut)';

    public function handle(ClientCardPaymentAllocationRepairService $repair): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply || (bool) $this->option('dry-run');

        if ($apply && $this->option('dry-run')) {
            $this->warn('Les options --apply et --dry-run sont incompatibles : mode dry-run conservé.');
            $dryRun = true;
        }

        $invoiceId = $this->resolveInvoiceId($this->option('invoice'));
        if ($this->option('invoice') && ! $invoiceId) {
            $this->error('Facture introuvable: '.$this->option('invoice'));

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '[APPLY] ').'Recherche des paiements carte non affectés…');

        $result = $repair->repair(! $dryRun, $invoiceId);

        if ($result['matched'] === 0) {
            $this->info('Aucun paiement carte orphelin à rattacher.');

            return self::SUCCESS;
        }

        $this->table(
            [
                'Facture',
                'Client',
                'Source',
                'Montant',
                'Date',
                'Référence',
                'Reste avant',
                'Statut',
            ],
            collect($result['rows'])->map(fn (array $row) => [
                $row['invoice_number'] ?? $row['invoice_id'],
                $row['client'] ?? '—',
                $row['source'] ?? '—',
                number_format((float) $row['amount'], 2, ',', ' ').' DH',
                $row['payment_date'] ?? '—',
                $row['reference'] ?? '—',
                number_format((float) ($row['remaining_before'] ?? 0), 2, ',', ' ').' DH',
                $row['status'] ?? '—',
            ])->all()
        );

        $this->info(sprintf(
            'Correspondances : %d — %s : %d — ignorés : %d',
            $result['matched'],
            $dryRun ? 'à allouer' : 'alloués',
            $result['allocated'],
            $result['skipped']
        ));

        if ($dryRun) {
            $this->warn('Aucune écriture effectuée. Relancer avec --apply pour créer les InvoicePayment et recalculer payé/reste/statut.');
        } else {
            $this->info('Rattachements appliqués (idempotent — un second passage ne crée pas de doublon).');
        }

        return self::SUCCESS;
    }

    private function resolveInvoiceId(mixed $option): ?int
    {
        if ($option === null || $option === '') {
            return null;
        }

        if (ctype_digit((string) $option)) {
            return (int) $option;
        }

        $id = \App\Models\Invoice::query()
            ->where('invoice_number', $option)
            ->value('id');

        return $id ? (int) $id : null;
    }
}
