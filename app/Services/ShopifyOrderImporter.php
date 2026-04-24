<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Product;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use Illuminate\Support\Facades\DB;

class ShopifyOrderImporter
{
    public function import(array $order): PosSale
    {
        return DB::transaction(function () use ($order) {
            $externalId = (string) ($order['id'] ?? '');
            if ($externalId === '') {
                throw new \InvalidArgumentException('Missing Shopify order id.');
            }

            // Extract Shopify statuses
            $paymentStatus = strtolower((string) ($order['financial_status'] ?? 'pending'));
            $fulfillmentStatus = $order['fulfillment_status'] 
                ? strtolower((string) $order['fulfillment_status']) 
                : 'unfulfilled';

            $existing = PosSale::query()
                ->where('source', 'shopify')
                ->where('external_id', $externalId)
                ->first();

            $client = $this->resolveClient($order);

            $lineRows = [];
            $subtotalHt = 0;
            $taxTotal = 0;

            foreach ($order['line_items'] ?? [] as $li) {
                if (! is_array($li)) {
                    continue;
                }
                if (($li['gift_card'] ?? false) === true) {
                    continue;
                }

                // Use current_quantity (after refunds/removals) if available,
                // otherwise fall back to original quantity
                $currentQty = array_key_exists('current_quantity', $li)
                    ? (int) $li['current_quantity']
                    : (int) ($li['quantity'] ?? 1);

                // Skip items that have been fully refunded/removed from the order
                if ($currentQty <= 0) {
                    continue;
                }

                $sku = trim((string) ($li['sku'] ?? ''));
                $product = $sku !== '' ? Product::query()->where('ref', $sku)->first() : null;

                $qty = max(1, $currentQty);
                $unitPriceTTC = (float) ($li['price'] ?? 0);

                $taxRate = $this->lineTaxRate($li, $product);
                
                // Shopify prices are TTC (including tax), so we need to calculate HT from TTC
                // Formula: HT = TTC / (1 + taxRate/100)
                $lineTotalTTC = round($qty * $unitPriceTTC, 2);
                $base = round($lineTotalTTC / (1 + ($taxRate / 100)), 2);
                $tax = round($lineTotalTTC - $base, 2);
                $lineTotal = $lineTotalTTC;
                
                // Calculate unit price HT for storage
                $unitPrice = $qty > 0 ? round($base / $qty, 2) : 0;

                $subtotalHt += $base;
                $taxTotal += $tax;

                $lineRows[] = [
                    'product' => $product,
                    'ref' => $sku !== '' ? $sku : null,
                    'designation' => (string) ($li['name'] ?? $li['title'] ?? 'Article'),
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'discount' => 0.0,
                    'line_total' => $lineTotal,
                ];
            }

            // Handle orders where all items have been refunded/removed
            if ($lineRows === []) {
                // If order already exists locally, update it and remove all line items
                if ($existing) {
                    $existing->update([
                        'payment_status' => $paymentStatus,
                        'fulfillment_status' => $fulfillmentStatus,
                        'shopify_synced_at' => now(),
                    ]);
                    PosSaleItem::where('pos_sale_id', $existing->id)->delete();

                    \Log::info('Shopify order fully refunded - cleared line items', [
                        'order_id' => $externalId,
                        'ticket_number' => $existing->ticket_number,
                    ]);

                    return $existing;
                }

                // New order with no valid line items (fully refunded before first sync)
                // Skip silently - nothing to create
                \Log::info('Shopify order has no active line items - skipping', [
                    'order_id' => $externalId,
                ]);
                throw new \InvalidArgumentException('Order has no active line items (all refunded).');
            }

            // Use current_* fields (post-refund) if available, otherwise fall back to original values
            $globalDiscount = round(
                (float) ($order['current_total_discounts'] ?? $order['total_discounts'] ?? 0),
                2
            );
            $totalBeforeGlobal = round($subtotalHt + $taxTotal, 2);
            $computedTotal = max(0, round($totalBeforeGlobal - $globalDiscount, 2));

            // Prefer current_total_price (reflects state after refunds/removals)
            // over total_price (original order total that doesn't change after refunds)
            $shopifyTotal = null;
            if (isset($order['current_total_price'])) {
                $shopifyTotal = round((float) $order['current_total_price'], 2);
            } elseif (isset($order['total_price'])) {
                $shopifyTotal = round((float) $order['total_price'], 2);
            }

            // If Shopify's total doesn't match our computed total from active line items,
            // prefer our computed total (this handles edge cases where Shopify's current_total_price
            // isn't updated correctly)
            if ($shopifyTotal !== null && abs($shopifyTotal - $computedTotal) > 1) {
                // Use computed total from active line items for accuracy
                $total = $computedTotal;
            } else {
                $total = $shopifyTotal !== null ? $shopifyTotal : $computedTotal;
            }

            $paymentMethod = $this->mapPaymentMethod($order);
            $financialStatus = strtolower((string) ($order['financial_status'] ?? ''));

            $amountReceived = null;
            $changeAmount = 0;
            if ($financialStatus === 'paid' || $financialStatus === 'partially_paid') {
                $amountReceived = $total;
            }

            // Use the actual Shopify order name (e.g., "#FTC8807" or "FTC8807")
            // Prioritize 'name' over 'order_number' because name has the prefix (FTC8836) while order_number is just numeric (8836)
            $orderNumber = (string) ($order['name'] ?? $order['order_number'] ?? $externalId);
            // Remove the "#" prefix if present
            $ticketNumber = ltrim($orderNumber, '#');

            $currency = (string) ($order['currency'] ?? 'MAD');
            $currencyLabel = strtoupper($currency).' — Shopify';

            // If order exists, update it and its line items
            if ($existing) {
                $oldItemCount = PosSaleItem::where('pos_sale_id', $existing->id)->count();
                
                $existing->update([
                    'client_id' => $client?->id,
                    'subtotal' => $subtotalHt,
                    'discount' => $globalDiscount,
                    'tax_total' => $taxTotal,
                    'total' => $total,
                    'payment_method' => $paymentMethod,
                    'amount_received' => $amountReceived,
                    'change_amount' => $changeAmount,
                    'payment_status' => $paymentStatus,
                    'fulfillment_status' => $fulfillmentStatus,
                    'shopify_synced_at' => now(),
                ]);

                // Delete old line items and recreate them
                $deleted = PosSaleItem::where('pos_sale_id', $existing->id)->delete();

                foreach ($lineRows as $row) {
                    PosSaleItem::create([
                        'pos_sale_id' => $existing->id,
                        'product_id' => $row['product']?->id,
                        'ref' => $row['ref'],
                        'designation' => $row['designation'],
                        'quantity' => $row['quantity'],
                        'unit_price' => $row['unit_price'],
                        'tax_rate' => $row['tax_rate'],
                        'discount' => $row['discount'],
                        'line_total' => $row['line_total'],
                    ]);
                }

                \Log::info('Updated Shopify order line items', [
                    'order_id' => $externalId,
                    'ticket_number' => $existing->ticket_number,
                    'old_items' => $oldItemCount,
                    'deleted_items' => $deleted,
                    'new_items' => count($lineRows),
                ]);

                return $existing;
            }

            // Create new order
            $sale = PosSale::create([
                'ticket_number' => $ticketNumber,
                'client_id' => $client?->id,
                'user_id' => null,
                'sold_at' => isset($order['created_at']) ? \Carbon\Carbon::parse($order['created_at']) : now(),
                'currency' => $currencyLabel,
                'subtotal' => $subtotalHt,
                'discount' => $globalDiscount,
                'tax_total' => $taxTotal,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'amount_received' => $amountReceived,
                'change_amount' => $changeAmount,
                'status' => PosSale::STATUS_COMPLETED,
                'payment_status' => $paymentStatus,
                'fulfillment_status' => $fulfillmentStatus,
                'shopify_synced_at' => now(),
                'notes' => 'Shopify order #'.$orderNumber.' (id '.$externalId.')',
                'source' => 'shopify',
                'external_id' => $externalId,
            ]);

            foreach ($lineRows as $row) {
                PosSaleItem::create([
                    'pos_sale_id' => $sale->id,
                    'product_id' => $row['product']?->id,
                    'ref' => $row['ref'],
                    'designation' => $row['designation'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'tax_rate' => $row['tax_rate'],
                    'discount' => $row['discount'],
                    'line_total' => $row['line_total'],
                ]);
            }

            return $sale;
        });
    }

    private function resolveClient(array $order): ?Client
    {
        $email = trim((string) ($order['email'] ?? ''));
        if ($email === '') {
            $customer = $order['customer'] ?? null;
            if (is_array($customer)) {
                $email = trim((string) ($customer['email'] ?? ''));
            }
        }

        $billing = is_array($order['billing_address'] ?? null) ? $order['billing_address'] : [];
        $name = trim((string) ($billing['name'] ?? ''));
        if ($name === '' && isset($order['customer']) && is_array($order['customer'])) {
            $c = $order['customer'];
            $name = trim(trim((string) ($c['first_name'] ?? '')).' '.trim((string) ($c['last_name'] ?? '')));
        }
        if ($name === '') {
            $name = 'Shopify customer';
        }

        if ($email !== '') {
            $client = Client::query()->where('email', $email)->first();
            if ($client) {
                return $client;
            }

            return Client::create([
                'name' => $name,
                'email' => $email,
                'phone' => $this->phoneFromOrder($order),
                'address' => $billing['address1'] ?? null,
                'city' => $billing['city'] ?? null,
                'country' => $billing['country'] ?? null,
            ]);
        }

        return null;
    }

    private function phoneFromOrder(array $order): ?string
    {
        $billing = is_array($order['billing_address'] ?? null) ? $order['billing_address'] : [];
        $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
        $p = $billing['phone'] ?? $customer['phone'] ?? null;

        return $p ? (string) $p : null;
    }

    private function lineTaxRate(array $lineItem, ?Product $product): float
    {
        $taxLines = $lineItem['tax_lines'] ?? [];
        if (is_array($taxLines) && count($taxLines) > 0) {
            $rate = (float) ($taxLines[0]['rate'] ?? 0);

            return round($rate * 100, 2);
        }

        return $this->defaultTaxRate($product);
    }

    private function defaultTaxRate(?Product $product): float
    {
        if (! $product) {
            return 20.0;
        }

        $vat = strtolower((string) ($product->vat_category ?? ''));
        if (str_contains($vat, '10') || str_contains($vat, 'réduit') || str_contains($vat, 'reduit')) {
            return 10.0;
        }
        if (str_contains($vat, '0') || str_contains($vat, 'exempt')) {
            return 0.0;
        }

        return 20.0;
    }

    private function mapPaymentMethod(array $order): string
    {
        // Check payment gateway names first (most reliable)
        $gateway = strtolower((string) ($order['gateway'] ?? ''));
        
        // Cash on Delivery detection
        if (str_contains($gateway, 'cod') || 
            str_contains($gateway, 'cash_on_delivery') || 
            str_contains($gateway, 'cash on delivery') ||
            str_contains($gateway, 'contre remboursement')) {
            return PosSale::PAYMENT_CASH;
        }
        
        // Bank transfer detection
        if (str_contains($gateway, 'bank') || 
            str_contains($gateway, 'transfer') || 
            str_contains($gateway, 'virement')) {
            return PosSale::PAYMENT_TRANSFER;
        }
        
        // Cheque detection
        if (str_contains($gateway, 'cheque') || str_contains($gateway, 'check')) {
            return PosSale::PAYMENT_CHEQUE;
        }
        
        // Manual payment (often used for cash)
        if (str_contains($gateway, 'manual')) {
            return PosSale::PAYMENT_CASH;
        }
        
        // Check payment gateway names in payment_gateway_names array
        $paymentGateways = $order['payment_gateway_names'] ?? [];
        if (is_array($paymentGateways)) {
            foreach ($paymentGateways as $gw) {
                $gwLower = strtolower((string) $gw);
                if (str_contains($gwLower, 'cod') || 
                    str_contains($gwLower, 'cash_on_delivery') ||
                    str_contains($gwLower, 'cash on delivery') ||
                    str_contains($gwLower, 'contre remboursement')) {
                    return PosSale::PAYMENT_CASH;
                }
                if (str_contains($gwLower, 'bank') || 
                    str_contains($gwLower, 'transfer') || 
                    str_contains($gwLower, 'virement')) {
                    return PosSale::PAYMENT_TRANSFER;
                }
                if (str_contains($gwLower, 'cheque') || str_contains($gwLower, 'check')) {
                    return PosSale::PAYMENT_CHEQUE;
                }
            }
        }
        
        // Check tags as fallback
        $tags = strtolower((string) ($order['tags'] ?? ''));
        if (str_contains($tags, 'cash') || str_contains($tags, 'cod')) {
            return PosSale::PAYMENT_CASH;
        }
        if (str_contains($tags, 'cheque') || str_contains($tags, 'check')) {
            return PosSale::PAYMENT_CHEQUE;
        }
        
        // Default to card for online payments
        return PosSale::PAYMENT_CARD;
    }
}
