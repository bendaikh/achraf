<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class DocumentSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $documentTypes = [
            'facture' => [
                'format' => 'FA-{YEAR}/{NUMBER}',
                'code_length' => 6,
                'reset_period' => 'yearly',
            ],
            'devis' => [
                'format' => 'DV-{YEAR}/{NUMBER}',
                'code_length' => 6,
                'reset_period' => 'yearly',
            ],
            'avoir' => [
                'format' => 'AV-{YEAR}/{NUMBER}',
                'code_length' => 6,
                'reset_period' => 'yearly',
            ],
            'bc_client' => [
                'format' => 'BC-{YEAR}/{NUMBER}',
                'code_length' => 6,
                'reset_period' => 'yearly',
            ],
            'bc_fournisseur' => [
                'format' => 'BCF-{YEAR}/{NUMBER}',
                'code_length' => 6,
                'reset_period' => 'yearly',
            ],
            'bon_livraison' => [
                'format' => 'BL-{YEAR}/{NUMBER}',
                'code_length' => 6,
                'reset_period' => 'yearly',
            ],
            'bon_reception' => [
                'format' => 'BR-{YEAR}/{NUMBER}',
                'code_length' => 6,
                'reset_period' => 'yearly',
            ],
            'produit' => [
                'format' => 'PRD-{NUMBER}',
                'code_length' => 6,
                'reset_period' => 'never',
            ],
        ];

        foreach ($documentTypes as $type => $config) {
            Setting::updateOrCreate(
                ['key' => "{$type}_next_number"],
                ['value' => '1', 'description' => "Prochain numéro de {$type}"]
            );

            Setting::updateOrCreate(
                ['key' => "{$type}_format"],
                ['value' => $config['format'], 'description' => "Format de numérotation de {$type}"]
            );

            Setting::updateOrCreate(
                ['key' => "{$type}_code_length"],
                ['value' => $config['code_length'], 'description' => "Longueur du code {$type}"]
            );

            Setting::updateOrCreate(
                ['key' => "{$type}_reset_period"],
                ['value' => $config['reset_period'], 'description' => "Période de réinitialisation {$type}"]
            );

            Setting::updateOrCreate(
                ['key' => "{$type}_year"],
                ['value' => date('Y'), 'description' => "Année de {$type}"]
            );

            Setting::updateOrCreate(
                ['key' => "{$type}_apply_to_old"],
                ['value' => '0', 'description' => "Appliquer aux anciens documents {$type}"]
            );
        }

        Setting::updateOrCreate(
            ['key' => 'devis_validity_days'],
            ['value' => '30', 'description' => 'Durée de validité des devis en jours']
        );

        Setting::updateOrCreate(
            ['key' => 'shopify_price_type'],
            ['value' => 'ttc', 'description' => 'Détermine si les prix des produits Shopify sont TTC ou HT']
        );

        $this->command->info('Document settings initialized successfully!');
    }
}
