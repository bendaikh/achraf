<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\SupplierAllocationRepairService;
use Illuminate\Console\Command;

class RepairSupplierPaymentAllocations extends Command
{
    protected $signature = 'suppliers:repair-payment-allocations
                            {--dry-run : Simuler sans écrire (défaut si --apply absent)}
                            {--apply : Appliquer les corrections en base}
                            {--supplier= : Limiter à un fournisseur (id ou nom partiel)}';

    protected $description = 'Audit/réparation idempotente des affectations règlements/avoirs fournisseurs (dry-run par défaut)';

    public function handle(SupplierAllocationRepairService $repair): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply || (bool) $this->option('dry-run');

        if ($apply && $this->option('dry-run')) {
            $this->warn('Les options --apply et --dry-run sont incompatibles : mode dry-run conservé.');
            $dryRun = true;
        }

        $supplierId = $this->resolveSupplierId($this->option('supplier'));
        if ($this->option('supplier') && ! $supplierId) {
            $this->error('Fournisseur introuvable: '.$this->option('supplier'));

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '[APPLY] ').'Audit des affectations fournisseurs…');

        $audits = $repair->auditAll($supplierId);
        if ($audits === []) {
            $this->info('Aucun fournisseur avec mouvement à auditer.');

            return self::SUCCESS;
        }

        $this->table(
            [
                'Fournisseur',
                'Factures',
                'Avoirs',
                'Règlements',
                'Non affecté',
                'Avoirs dispo.',
                'Factures ouvertes',
                'Solde réel',
                'Écart ouvertes/solde',
                'Orphelins',
                'Lignes manquantes',
            ],
            collect($audits)->map(fn (array $row) => [
                $row['supplier'],
                number_format($row['total_invoices'], 2, ',', ' '),
                number_format($row['total_credits'], 2, ',', ' '),
                number_format($row['total_payments'], 2, ',', ' '),
                number_format($row['unallocated_amount'], 2, ',', ' '),
                number_format($row['available_credits'], 2, ',', ' '),
                number_format($row['open_invoices'], 2, ',', ' '),
                number_format($row['balance'], 2, ',', ' '),
                number_format($row['open_vs_balance_gap'], 2, ',', ' '),
                number_format($row['orphan_unallocated'], 2, ',', ' '),
                $row['missing_invoice_lines'],
            ])->all()
        );

        $toRepair = collect($audits)->where('needs_repair', true)->values();
        $this->info('Fournisseurs à réparer : '.$toRepair->count().' / '.count($audits));

        if ($toRepair->isEmpty()) {
            $this->info('Aucune incohérence d’affectation détectée.');

            return self::SUCCESS;
        }

        $results = [];
        foreach ($toRepair as $row) {
            $supplier = Supplier::query()->find($row['supplier_id']);
            if (! $supplier) {
                continue;
            }
            $results[] = $repair->repairSupplier($supplier, ! $dryRun);
        }

        $this->table(
            [
                'Fournisseur',
                'Orphelins restaurés',
                'Lignes créées',
                'Avoirs imputés',
                'Avances imputées',
                'Solde avant',
                'Ouvertes avant',
                'Solde après',
                'Ouvertes après',
            ],
            collect($results)->map(fn (array $row) => [
                $row['supplier'],
                number_format($row['orphan_restored'], 2, ',', ' '),
                $row['invoice_lines_created'],
                number_format($row['credits_applied'], 2, ',', ' '),
                number_format($row['advances_applied'], 2, ',', ' '),
                number_format($row['before']['balance'], 2, ',', ' '),
                number_format($row['before']['open_invoices'], 2, ',', ' '),
                number_format($row['after']['balance'], 2, ',', ' '),
                number_format($row['after']['open_invoices'], 2, ',', ' '),
            ])->all()
        );

        if ($dryRun) {
            $this->warn('Aucune écriture effectuée. Relancer avec --apply pour affecter avoirs/avances aux factures ouvertes.');
        } else {
            $this->info('Réparations appliquées (idempotent).');
        }

        return self::SUCCESS;
    }

    private function resolveSupplierId(mixed $option): ?int
    {
        if ($option === null || $option === '') {
            return null;
        }

        if (ctype_digit((string) $option)) {
            return (int) $option;
        }

        $id = Supplier::query()
            ->where('name', 'like', '%'.$option.'%')
            ->orderBy('name')
            ->value('id');

        return $id ? (int) $id : null;
    }
}
