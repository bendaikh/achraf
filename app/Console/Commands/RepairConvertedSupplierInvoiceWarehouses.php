<?php

namespace App\Console\Commands;

use App\Services\SupplierInvoiceLineWarehouseBackfillService;
use Illuminate\Console\Command;

class RepairConvertedSupplierInvoiceWarehouses extends Command
{
    protected $signature = 'supplier-invoices:repair-converted-warehouses
                            {--dry-run : Simuler sans écrire (défaut si --apply absent)}
                            {--apply : Appliquer les corrections en base}
                            {--invoice= : Limiter à une facture (id ou numéro FSI-…)}';

    protected $description = 'Backfill dépôt/emplacement des lignes de factures converties depuis BL/BR (idempotent, sans nouvelle entrée stock)';

    public function handle(SupplierInvoiceLineWarehouseBackfillService $service): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply || (bool) $this->option('dry-run');

        if ($apply && $this->option('dry-run')) {
            $this->warn('Les options --apply et --dry-run sont incompatibles : mode dry-run conservé.');
            $dryRun = true;
        }

        $invoiceOption = $this->option('invoice');
        $invoiceId = null;
        if ($invoiceOption !== null && $invoiceOption !== '') {
            if (ctype_digit((string) $invoiceOption)) {
                $invoiceId = (int) $invoiceOption;
            } else {
                $invoiceId = \App\Models\SupplierInvoice::query()
                    ->where('invoice_number', $invoiceOption)
                    ->value('id');
                if (! $invoiceId) {
                    $this->error('Facture introuvable: '.$invoiceOption);

                    return self::FAILURE;
                }
            }
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '').'Réparation des dépôts/emplacements sur factures converties…');

        $result = $service->backfill($dryRun, $invoiceId);

        $this->info("Factures scannées : {$result['scanned']}");
        $this->info("Lignes à mettre à jour : {$result['updated_lines']}");
        $this->info("Factures sans allocations à copier : {$result['mirrored_allocations']}");
        $this->info("Factures à marquer stock_applied_at : {$result['stamped_stock']}");

        if (! empty($result['details'])) {
            $this->table(
                ['Facture', 'Ligne', 'Produit', 'Dépôt', 'Emplacement', 'Source'],
                collect($result['details'])->map(fn (array $row) => [
                    $row['invoice'],
                    $row['item_id'],
                    $row['product_id'],
                    $row['warehouse_id'],
                    $row['warehouse_location_id'] ?? '—',
                    $row['source'],
                ])->all()
            );
        }

        if ($dryRun) {
            $this->warn('Aucune écriture effectuée. Relancer avec --apply pour corriger.');
        } else {
            $this->info('Corrections appliquées (aucune nouvelle entrée stock).');
        }

        return self::SUCCESS;
    }
}
