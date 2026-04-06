<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Filtre à air haute performance',
                'ref' => 'FLT-001',
                'cost_price_ht' => 45.00,
                'cost_price_ttc' => 54.00,
                'last_purchase_price' => 43.50,
                'sale_price' => 75.00,
                'minimum_safety_stock' => 10,
                'minimum_alert_stock' => 5,
                'stock_quantity' => 24,
                'barcode' => '3456789012345',
                'vat_category' => 'TVA (20%)',
                'element_type' => 'Produit',
                'tag' => 'Performance',
                'status' => 'Activer',
                'product_category' => 'Pièces moteur',
                'description' => 'Filtre à air haute performance pour améliorer les performances du moteur.',
            ],
            [
                'name' => 'Échappement sport inox',
                'ref' => 'ECH-002',
                'cost_price_ht' => 350.00,
                'cost_price_ttc' => 420.00,
                'last_purchase_price' => 340.00,
                'sale_price' => 650.00,
                'minimum_safety_stock' => 3,
                'minimum_alert_stock' => 2,
                'stock_quantity' => 2,
                'barcode' => '3456789012346',
                'vat_category' => 'TVA (20%)',
                'element_type' => 'Produit',
                'tag' => 'Tuning',
                'status' => 'Activer',
                'product_category' => 'Échappement',
                'description' => 'Échappement sport en acier inoxydable pour un son et des performances améliorés.',
            ],
            [
                'name' => 'Kit suspension sport',
                'ref' => 'SUS-003',
                'cost_price_ht' => 450.00,
                'cost_price_ttc' => 540.00,
                'last_purchase_price' => 445.00,
                'sale_price' => 850.00,
                'minimum_safety_stock' => 2,
                'minimum_alert_stock' => 1,
                'stock_quantity' => 4,
                'barcode' => '3456789012347',
                'vat_category' => 'TVA (20%)',
                'element_type' => 'Produit',
                'tag' => 'Performance',
                'status' => 'Activer',
                'product_category' => 'Suspension',
                'description' => 'Kit suspension sport complet pour améliorer la tenue de route.',
            ],
            [
                'name' => 'Installation reprogrammation moteur',
                'ref' => 'SRV-001',
                'cost_price_ht' => 0.00,
                'cost_price_ttc' => 0.00,
                'last_purchase_price' => 0.00,
                'sale_price' => 400.00,
                'minimum_safety_stock' => null,
                'minimum_alert_stock' => null,
                'stock_quantity' => 0,
                'barcode' => null,
                'vat_category' => 'TVA (20%)',
                'element_type' => 'Service',
                'tag' => 'Service',
                'status' => 'Activer',
                'product_category' => 'Services',
                'description' => 'Service de reprogrammation moteur pour optimiser les performances.',
            ],
            [
                'name' => 'Pneu sport haute performance 225/45 R18',
                'ref' => 'PNE-004',
                'cost_price_ht' => 120.00,
                'cost_price_ttc' => 144.00,
                'last_purchase_price' => 118.00,
                'sale_price' => 200.00,
                'minimum_safety_stock' => 20,
                'minimum_alert_stock' => 10,
                'stock_quantity' => 8,
                'barcode' => '3456789012348',
                'vat_category' => 'TVA (20%)',
                'element_type' => 'Produit',
                'tag' => 'Pneus',
                'status' => 'Activer',
                'product_category' => 'Pneumatiques',
                'description' => 'Pneu sport haute performance pour une adhérence optimale.',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
