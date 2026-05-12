<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    protected $documentTypes = [
        'facture', 'devis', 'avoir', 'bc_fournisseur', 'bc_client', 
        'bon_livraison', 'bon_reception', 'produit'
    ];

    protected $settingFields = [
        'next_number', 'format', 'apply_to_old', 'year', 
        'code_length', 'reset_period', 'conditions', 'remarks'
    ];

    public function index()
    {
        $settings = $this->getAllSettings();
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $settingsType = $request->input('settings_type');
        $tab = $settingsType;

        if ($settingsType && in_array($settingsType, $this->documentTypes)) {
            $this->saveDocumentSettings($request, $settingsType);
        }

        if ($request->has('shopify_price_type')) {
            Setting::set(
                'shopify_price_type',
                $request->input('shopify_price_type'),
                'Détermine si les prix des produits Shopify sont TTC ou HT'
            );
        }

        return redirect()->route('settings.index', ['tab' => $tab])->with('success', 'Paramètres mis à jour avec succès.');
    }

    protected function getAllSettings(): array
    {
        $settings = [];
        
        foreach ($this->documentTypes as $type) {
            foreach ($this->settingFields as $field) {
                $key = "{$type}_{$field}";
                $settings[$key] = Setting::get($key);
            }
            if ($type === 'devis') {
                $settings['devis_validity_days'] = Setting::get('devis_validity_days', '30');
            }
        }

        $settings['shopify_price_type'] = Setting::getShopifyPriceType();

        return $settings;
    }

    protected function saveDocumentSettings(Request $request, string $type): void
    {
        $prefix = $type . '_';

        $fields = [
            'next_number' => "Prochain numéro de {$type}",
            'format' => "Format de numérotation de {$type}",
            'apply_to_old' => "Appliquer aux anciens documents {$type}",
            'year' => "Année de {$type}",
            'code_length' => "Longueur du code {$type}",
            'reset_period' => "Période de réinitialisation {$type}",
            'conditions' => "Conditions par défaut {$type}",
            'remarks' => "Remarques par défaut {$type}",
        ];

        foreach ($fields as $field => $description) {
            $inputKey = $prefix . $field;
            if ($request->has($inputKey)) {
                $value = $request->input($inputKey);
                if ($field === 'apply_to_old') {
                    $value = $request->has($inputKey) ? '1' : '0';
                }
                Setting::set($inputKey, $value, $description);
            } elseif ($field === 'apply_to_old') {
                Setting::set($inputKey, '0', $description);
            }
        }

        if ($type === 'devis' && $request->has('devis_validity_days')) {
            Setting::set('devis_validity_days', $request->input('devis_validity_days'), 'Durée de validité des devis en jours');
        }
    }
}
