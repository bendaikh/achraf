<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;

class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::create([
            'name' => 'Fournisseur Auto Parts',
            'email' => 'contact@autoparts.ma',
            'phone' => '+212 600 111 111',
            'address' => '123 Rue Industrielle',
            'city' => 'Casablanca',
            'country' => 'MAD',
            'tax_id' => 'SUP123456',
        ]);

        Supplier::create([
            'name' => 'Garage Equipment Supply',
            'email' => 'info@garagesupply.ma',
            'phone' => '+212 661 222 222',
            'address' => '456 Boulevard Commerce',
            'city' => 'Rabat',
            'country' => 'MAD',
            'tax_id' => 'SUP789012',
        ]);
    }
}
