<?php

namespace App\Console\Commands;

use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\ShopifyIntegration;
use App\Services\ShopifyApiClient;
use Illuminate\Console\Command;

class InspectShopifyOrder extends Command
{
    protected $signature = 'shopify:inspect-order 
                            {ticket : The ticket number (e.g., FTC8839) or Shopify order ID}';

    protected $description = 'Inspect a Shopify order and compare with local data';

    public function handle(): int
    {
        $ticket = $this->argument('ticket');
        
        $integration = ShopifyIntegration::query()->first();

        if (!$integration) {
            $this->error('No Shopify integration configured.');
            return self::FAILURE;
        }

        // Find local order
        $localSale = PosSale::query()
            ->where('source', 'shopify')
            ->where(function ($q) use ($ticket) {
                $q->where('ticket_number', $ticket)
                  ->orWhere('ticket_number', ltrim($ticket, '#'))
                  ->orWhere('external_id', $ticket);
            })
            ->first();

        if (!$localSale) {
            $this->error("Local order not found for ticket: {$ticket}");
            return self::FAILURE;
        }

        $this->info('═══════════════════════════════════════');
        $this->info("  Local Order: {$localSale->ticket_number}");
        $this->info('═══════════════════════════════════════');
        $this->line("  ID: {$localSale->id}");
        $this->line("  External ID: {$localSale->external_id}");
        $this->line("  Total: {$localSale->total}");
        $this->line("  Updated at: {$localSale->updated_at}");
        $this->line("  Shopify synced at: {$localSale->shopify_synced_at}");
        $this->newLine();

        $localItems = PosSaleItem::where('pos_sale_id', $localSale->id)->get();
        $this->info("Local line items ({$localItems->count()}):");
        
        $headers = ['ID', 'Ref', 'Designation', 'Qty', 'Unit Price', 'Line Total'];
        $rows = [];
        foreach ($localItems as $item) {
            $rows[] = [
                $item->id,
                $item->ref ?? '-',
                substr($item->designation, 0, 40),
                $item->quantity,
                $item->unit_price,
                $item->line_total,
            ];
        }
        $this->table($headers, $rows);

        // Fetch from Shopify
        $this->info('Fetching order from Shopify API...');
        $client = new ShopifyApiClient($integration);
        $shopifyOrder = $client->getOrder($localSale->external_id);

        if (!$shopifyOrder) {
            $this->error('Order not found in Shopify.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info("  Shopify Order: {$shopifyOrder['name']}");
        $this->info('═══════════════════════════════════════');
        $this->line("  ID: {$shopifyOrder['id']}");
        $this->line("  Total: {$shopifyOrder['total_price']}");
        $this->line("  Updated at: {$shopifyOrder['updated_at']}");
        $this->line("  Financial status: " . ($shopifyOrder['financial_status'] ?? 'N/A'));
        $this->line("  Fulfillment status: " . ($shopifyOrder['fulfillment_status'] ?? 'N/A'));
        $this->newLine();

        $lineItems = $shopifyOrder['line_items'] ?? [];
        $this->info("Shopify line items (" . count($lineItems) . "):");
        
        $headers = ['ID', 'SKU', 'Title', 'Qty', 'Current Qty', 'Price', 'Refunded'];
        $rows = [];
        foreach ($lineItems as $item) {
            $currentQty = $item['current_quantity'] ?? 'N/A';
            $refunded = isset($item['fulfillable_quantity']) && $item['fulfillable_quantity'] === 0 ? 'Yes' : 'No';
            $rows[] = [
                $item['id'] ?? '-',
                $item['sku'] ?? '-',
                substr($item['title'] ?? $item['name'] ?? '', 0, 40),
                $item['quantity'] ?? 0,
                $currentQty,
                $item['price'] ?? 0,
                $refunded,
            ];
        }
        $this->table($headers, $rows);

        // Check for refunds
        $refunds = $shopifyOrder['refunds'] ?? [];
        if (!empty($refunds)) {
            $this->newLine();
            $this->warn('⚠ This order has refunds:');
            foreach ($refunds as $refund) {
                $this->line("  - Refund created at: " . ($refund['created_at'] ?? 'N/A'));
                foreach ($refund['refund_line_items'] ?? [] as $rli) {
                    $this->line("    - Line item {$rli['line_item_id']}: qty refunded = {$rli['quantity']}");
                }
            }
        }

        // Output raw JSON for debugging
        $this->newLine();
        if ($this->confirm('Show raw line_items JSON for debugging?', false)) {
            $this->line(json_encode($lineItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
