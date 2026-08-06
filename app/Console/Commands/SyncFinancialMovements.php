<?php

namespace App\Console\Commands;

use App\Services\FinancialMovementService;
use Illuminate\Console\Command;

class SyncFinancialMovements extends Command
{
    protected $signature = 'financial:sync-movements {--fresh : Delete auto-generated movements before syncing}';

    protected $description = 'Synchronise le journal des mouvements financiers depuis les paiements, dépenses et ventes POS';

    public function handle(FinancialMovementService $movements): int
    {
        if ($this->option('fresh')) {
            $deleted = \App\Models\FinancialMovement::query()
                ->where('is_manual', false)
                ->whereNull('day_closed_at')
                ->delete();
            $this->info("{$deleted} mouvement(s) automatiques supprimés.");
        }

        $this->info('Synchronisation en cours…');
        $result = $movements->backfill();
        $this->info("Terminé : {$result['created']} traité(s), {$result['skipped']} ignoré(s).");

        return self::SUCCESS;
    }
}
