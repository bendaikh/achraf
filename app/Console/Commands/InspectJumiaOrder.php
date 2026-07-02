<?php

namespace App\Console\Commands;

use App\Models\JumiaIntegration;
use App\Models\PosSale;
use App\Services\Jumia\JumiaApiClient;
use Illuminate\Console\Command;

class InspectJumiaOrder extends Command
{
    protected $signature = 'jumia:inspect-order {externalId? : Jumia order external id or ticket number}';

    protected $description = 'Inspect raw Jumia API order/item payload for debugging';

    public function handle(): int
    {
        $integration = JumiaIntegration::query()->first();

        if (! $integration?->isConfigured()) {
            $this->error('Jumia integration is not configured.');

            return self::FAILURE;
        }

        $lookup = $this->argument('externalId');

        if ($lookup) {
            $sale = PosSale::query()
                ->where('source', 'jumia')
                ->where(function ($q) use ($lookup) {
                    $q->where('external_id', $lookup)
                        ->orWhere('ticket_number', $lookup)
                        ->orWhere('ticket_number', 'JUM-'.$lookup);
                })
                ->first();
        } else {
            $sale = PosSale::query()->where('source', 'jumia')->latest()->first();
        }

        if (! $sale) {
            $this->error('No matching Jumia order found locally.');

            return self::FAILURE;
        }

        $client = new JumiaApiClient($integration);

        $this->info('Local order: '.$sale->ticket_number.' (external_id: '.$sale->external_id.')');
        $this->line('Stored total: '.$sale->total);

        $items = $client->getOrderItems((string) $sale->external_id);

        $this->newLine();
        $this->info('Raw order items from Jumia API:');
        $this->line(json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $page = $client->getOrders(['size' => 5]);
        $match = collect($page)->first(fn ($order) => (string) ($order['id'] ?? '') === (string) $sale->external_id);

        if ($match) {
            $this->newLine();
            $this->info('Matching order from /orders list:');
            $this->line(json_encode($match, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
