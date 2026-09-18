<?php

namespace App\Console\Commands;

use App\Models\FinancialMovement;
use App\Models\PosSale;
use App\Support\OrderSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class NeutralizeChannelPosMovements extends Command
{
    protected $signature = 'financial:neutralize-channel-pos
                            {--dry-run : Lister sans modifier (défaut si --apply absent)}
                            {--apply : Neutraliser réellement les faux mouvements}';

    protected $description = 'Audite et neutralise les faux encaissements POS issus de commandes Shopify/Jumia/Libromart (sans supprimer les commandes)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply || (bool) $this->option('dry-run');

        if ($apply && $this->option('dry-run')) {
            $this->warn('Les options --apply et --dry-run sont incompatibles : mode dry-run conservé.');
            $dryRun = true;
            $apply = false;
        }

        $channelSources = OrderSource::channelSources();

        $query = FinancialMovement::query()
            ->where('source_type', (new PosSale)->getMorphClass())
            ->where('is_manual', false)
            ->whereHasMorph('source', [PosSale::class], function ($q) use ($channelSources) {
                $q->whereIn('source', $channelSources);
            });

        $movements = $query->with('source')->orderBy('id')->get();

        if ($movements->isEmpty()) {
            $this->info('Aucun faux mouvement POS lié à Shopify/Jumia/Libromart.');

            return self::SUCCESS;
        }

        $auditRows = [];
        $deleted = 0;
        $neutralized = 0;
        $skippedClosed = 0;

        foreach ($movements as $movement) {
            /** @var PosSale|null $sale */
            $sale = $movement->source;
            $row = [
                'movement_id' => $movement->id,
                'reference' => $movement->reference,
                'movement_date' => optional($movement->movement_date)->format('Y-m-d'),
                'origin' => $movement->origin,
                'label' => $movement->label,
                'account' => $movement->account,
                'amount_in' => (float) $movement->amount_in,
                'amount_out' => (float) $movement->amount_out,
                'status' => $movement->status,
                'day_closed_at' => optional($movement->day_closed_at)->toIso8601String(),
                'pos_sale_id' => $sale?->id,
                'pos_source' => $sale?->source,
                'ticket_number' => $sale?->ticket_number,
                'order_external_id' => $sale?->external_id,
                'payment_status' => $sale?->payment_status,
                'action' => 'none',
            ];

            $locked = $movement->day_closed_at !== null
                || $movement->status === FinancialMovement::STATUS_CLOTURE;

            if ($dryRun) {
                $row['action'] = $locked ? 'would_neutralize_closed' : 'would_delete';
                $auditRows[] = $row;
                continue;
            }

            if ($locked) {
                // Conserver la trace : zéro + motif, sans supprimer la période clôturée.
                $movement->update([
                    'amount_in' => 0,
                    'amount_out' => 0,
                    'notes' => trim(implode("\n", array_filter([
                        $movement->notes,
                        '[NETTOYAGE '.now()->toDateTimeString().'] Faux encaissement POS neutralisé : commande canal '.$sale?->source.' (ticket '.$sale?->ticket_number.'). Commande conservée.',
                    ]))),
                ]);
                $row['action'] = 'neutralized_closed';
                $neutralized++;
            } else {
                $movement->delete();
                $row['action'] = 'deleted';
                $deleted++;
            }

            $auditRows[] = $row;
        }

        $auditPath = 'audits/channel-pos-neutralize-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($auditPath, json_encode([
            'ran_at' => now()->toIso8601String(),
            'dry_run' => $dryRun,
            'channel_sources' => $channelSources,
            'count' => count($auditRows),
            'deleted' => $deleted,
            'neutralized' => $neutralized,
            'rows' => $auditRows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->table(
            ['ID', 'Date', 'Canal', 'Ticket', 'Montant', 'Statut', 'Action'],
            collect($auditRows)->map(fn (array $r) => [
                $r['movement_id'],
                $r['movement_date'],
                $r['pos_source'] ?? '—',
                $r['ticket_number'] ?? '—',
                number_format($r['amount_in'], 2, ',', ' '),
                $r['status'],
                $r['action'],
            ])->all()
        );

        $this->info(($dryRun ? '[DRY-RUN] ' : '').count($auditRows).' mouvement(s) audité(s).');
        if (! $dryRun) {
            $this->info("Supprimés : {$deleted} — Neutralisés (clôturés) : {$neutralized}");
        }
        $this->info('Trace audit : storage/app/'.$auditPath);

        return self::SUCCESS;
    }
}
