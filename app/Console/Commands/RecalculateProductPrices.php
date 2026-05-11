<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Console\Command;

class RecalculateProductPrices extends Command
{
    protected $signature = 'products:recalculate-prices';
    protected $description = 'Recalculate HT prices for products that have TTC prices but no HT prices';

    public function handle()
    {
        $priceType = Setting::getShopifyPriceType();
        $defaultTaxRate = 20;
        
        $this->info("Current Shopify price type: {$priceType}");
        $this->info("Default tax rate: {$defaultTaxRate}%");
        
        $products = Product::whereNull('sale_price_ht')
            ->whereNotNull('sale_price')
            ->where('sale_price', '>', 0)
            ->get();
        
        $count = $products->count();
        $this->info("Found {$count} products with TTC price but no HT price.");
        
        if ($count === 0) {
            $this->info('No products need updating.');
            return 0;
        }
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        foreach ($products as $product) {
            if ($priceType === 'ttc') {
                $salePriceHT = round($product->sale_price / (1 + $defaultTaxRate / 100), 2);
            } else {
                $salePriceHT = $product->sale_price;
            }
            
            $product->update(['sale_price_ht' => $salePriceHT]);
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("Successfully updated {$count} products.");
        
        return 0;
    }
}
