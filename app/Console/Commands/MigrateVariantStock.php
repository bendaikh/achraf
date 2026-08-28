<?php

namespace App\Console\Commands;

use App\Services\VariantStockMigrationService;
use Illuminate\Console\Command;

class MigrateVariantStock extends Command
{
    protected $signature = 'products:migrate-variant-stock
                            {--dry-run : Simuler sans modifier la base}
                            {--report : Afficher le rapport détaillé}';

    protected $description = 'Répartit le stock des produits Shopify multi-variantes au niveau de chaque variante';

    public function handle(VariantStockMigrationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $showReport = (bool) $this->option('report') || $dryRun;

        if ($dryRun) {
            $this->warn('Mode simulation — aucune modification en base.');
        }

        $report = $service->migrate($dryRun);

        $this->info('Produits multi-variantes traités : '.$report['migrated']);

        if ($showReport) {
            $this->printSection('Variantes sans SKU', $report['missing_sku']);
            $this->printSection('Variantes sans Shopify Variant ID', $report['missing_shopify_variant_id']);
            $this->printSection('Doublons de SKU', $report['duplicates']);
            $this->printSection('Vérification manuelle requise', $report['manual_review']);
        }

        if (! $dryRun) {
            $this->info('Migration terminée. Relancez avec --report pour le détail.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function printSection(string $title, array $rows): void
    {
        $this->newLine();
        $this->line('<fg=cyan>'.$title.' ('.count($rows).')</>');

        if ($rows === []) {
            $this->line('  Aucun.');

            return;
        }

        foreach (array_slice($rows, 0, 20) as $row) {
            $this->line('  • '.json_encode($row, JSON_UNESCAPED_UNICODE));
        }

        if (count($rows) > 20) {
            $this->line('  … et '.(count($rows) - 20).' de plus');
        }
    }
}
