<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Product;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        Client::create([
            'name' => 'Fast Tuning Car',
            'email' => 'contact@fasttuningcar.com',
            'phone' => '+212 600 000 000',
            'address' => '123 Rue Example',
            'city' => 'Casablanca',
            'country' => 'MAD',
            'tax_id' => 'TAX123456',
        ]);

        Client::create([
            'name' => 'Auto Service Plus',
            'email' => 'info@autoserviceplus.ma',
            'phone' => '+212 661 111 111',
            'address' => '456 Avenue Mohammed V',
            'city' => 'Rabat',
            'country' => 'MAD',
            'tax_id' => 'TAX789012',
        ]);

        Client::create([
            'name' => 'Garage Premium',
            'email' => 'contact@garagepremium.ma',
            'phone' => '+212 662 222 222',
            'address' => '789 Boulevard Anfa',
            'city' => 'Casablanca',
            'country' => 'MAD',
            'tax_id' => 'TAX345678',
        ]);

        Product::create([
            'ref' => 'PROD-001',
            'designation' => 'Huile moteur 5W40',
            'description' => 'Huile moteur synthétique haute performance',
            'unit_price' => 350.00,
            'tax_rate' => 20.00,
            'stock_quantity' => 100,
            'stock_location' => 'DEPOT',
        ]);

        Product::create([
            'ref' => 'PROD-002',
            'designation' => 'Filtre à huile',
            'description' => 'Filtre à huile universel',
            'unit_price' => 85.00,
            'tax_rate' => 20.00,
            'stock_quantity' => 200,
            'stock_location' => 'DEPOT',
        ]);

        Product::create([
            'ref' => 'PROD-003',
            'designation' => 'Plaquettes de frein',
            'description' => 'Plaquettes de frein avant',
            'unit_price' => 450.00,
            'tax_rate' => 20.00,
            'stock_quantity' => 50,
            'stock_location' => 'DEPOT',
        ]);

        Product::create([
            'ref' => 'PROD-004',
            'designation' => 'Batterie 12V 70Ah',
            'description' => 'Batterie automobile standard',
            'unit_price' => 850.00,
            'tax_rate' => 20.00,
            'stock_quantity' => 30,
            'stock_location' => 'DEPOT',
        ]);

        Product::create([
            'ref' => 'PROD-005',
            'designation' => 'Pneu 205/55 R16',
            'description' => 'Pneu toutes saisons',
            'unit_price' => 650.00,
            'tax_rate' => 20.00,
            'stock_quantity' => 40,
            'stock_location' => 'DEPOT',
        ]);
    }
}
