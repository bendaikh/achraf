<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    protected $documentTypes = [
        'facture', 'devis', 'avoir', 'bc_fournisseur', 'bc_client',
        'bon_livraison', 'bon_reception', 'produit',
    ];

    protected $settingFields = [
        'next_number', 'format', 'apply_to_old', 'year',
        'code_length', 'reset_period', 'conditions', 'remarks',
    ];

    protected array $companyFields = [
        'company_name', 'company_subtitle', 'company_address', 'company_country', 'company_city',
        'company_postal_code', 'company_phone', 'company_ice', 'company_patente',
        'company_rc', 'company_if', 'company_cnss', 'company_email',
    ];

    public function index()
    {
        $settings = $this->getAllSettings();
        $previews = $this->getPreviewNumbers();

        return view('settings.index', compact('settings', 'previews'));
    }

    public function update(Request $request)
    {
        $settingsType = $request->input('settings_type');
        $tab = $settingsType;

        if ($settingsType && in_array($settingsType, $this->documentTypes, true)) {
            $this->saveDocumentSettings($request, $settingsType);
        }

        if ($settingsType === 'mon_entreprise') {
            $this->saveCompanySettings($request);
        }

        if ($settingsType === 'depenses') {
            $this->saveExpenseParameterSettings($request);
        }

        if ($settingsType === 'produit_types') {
            $this->saveListFromTextarea($request, 'product_element_types', 'Types d\'élément produit');
            $tab = 'produit';
        }

        if ($settingsType === 'vat_categories') {
            $this->saveListFromTextarea($request, 'vat_categories', 'Catégories TVA');
            $tab = 'categorie_tva';
        }

        if ($settingsType === 'product_type_categories') {
            $this->saveListFromTextarea($request, 'product_type_categories', 'Catégories de type produit');
            $tab = 'type_produit';
        }

        if ($request->has('shopify_price_type')) {
            Setting::set(
                'shopify_price_type',
                $request->input('shopify_price_type'),
                'Détermine si les prix des produits Shopify sont TTC ou HT'
            );
            $tab = $tab ?: 'produit';
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

        foreach ($this->companyFields as $field) {
            $settings[$field] = Setting::get($field, '');
        }
        $settings['company_logo'] = Setting::get('company_logo');
        $settings['company_cachet'] = Setting::get('company_cachet');

        $settings['expense_categories'] = implode("\n", Setting::getList('expense_categories'));
        $settings['expense_accounts'] = implode("\n", Setting::getList('expense_accounts'));
        $settings['expense_payment_methods'] = implode("\n", Setting::getList('expense_payment_methods'));
        $settings['product_element_types'] = implode("\n", Setting::getList('product_element_types', ['Produit', 'Service']));
        $settings['vat_categories'] = implode("\n", Setting::getList('vat_categories', \App\Support\VatCategoryHelper::defaultCategories()));
        $settings['product_type_categories'] = implode("\n", Setting::getList('product_type_categories', ['Électronique', 'Textile', 'Alimentaire', 'Service']));
        $settings['auto_invoice_start_order_number'] = Setting::get('auto_invoice_start_order_number', '');

        return $settings;
    }

    protected function getPreviewNumbers(): array
    {
        $previews = [];

        foreach ($this->documentTypes as $type) {
            $previews[$type] = DocumentNumberService::preview($type);
        }

        return $previews;
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

        if ($type === 'facture' && $request->has('auto_invoice_start_order_number')) {
            Setting::set(
                'auto_invoice_start_order_number',
                trim((string) $request->input('auto_invoice_start_order_number')),
                'Numéro de commande de départ pour la génération automatique de factures'
            );
        }
    }

    protected function saveCompanySettings(Request $request): void
    {
        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_subtitle' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:500',
            'company_country' => 'nullable|string|max:100',
            'company_city' => 'nullable|string|max:100',
            'company_postal_code' => 'nullable|string|max:20',
            'company_phone' => 'nullable|string|max:50',
            'company_ice' => 'nullable|string|max:50',
            'company_patente' => 'nullable|string|max:50',
            'company_rc' => 'nullable|string|max:50',
            'company_if' => 'nullable|string|max:50',
            'company_cnss' => 'nullable|string|max:50',
            'company_email' => 'nullable|string|max:255',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'company_cachet' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_logo' => 'nullable|boolean',
            'remove_cachet' => 'nullable|boolean',
        ]);

        foreach ($this->companyFields as $field) {
            if (array_key_exists($field, $validated)) {
                Setting::set($field, $validated[$field] ?? '', 'Informations entreprise');
            }
        }

        if ($request->boolean('remove_logo')) {
            $old = Setting::get('company_logo');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            Setting::set('company_logo', null, 'Logo entreprise');
        }

        if ($request->hasFile('company_logo')) {
            $old = Setting::get('company_logo');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('company_logo')->store('company', 'public');
            Setting::set('company_logo', $path, 'Logo entreprise');
        }

        if ($request->boolean('remove_cachet')) {
            $old = Setting::get('company_cachet');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            Setting::set('company_cachet', null, 'Cachet entreprise');
            \App\Support\CompanyInfo::forgetCachetBoostCache();
        }

        if ($request->hasFile('company_cachet')) {
            $old = Setting::get('company_cachet');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('company_cachet')->store('company', 'public');
            Setting::set('company_cachet', $path, 'Cachet entreprise');
            \App\Support\CompanyInfo::forgetCachetBoostCache();
        }
    }

    protected function saveExpenseParameterSettings(Request $request): void
    {
        $this->saveListFromTextarea($request, 'expense_categories', 'Catégories de dépense');
        $this->saveListFromTextarea($request, 'expense_accounts', 'Comptes de dépense');
        $this->saveListFromTextarea($request, 'expense_payment_methods', 'Modes de règlement');
    }

    protected function saveListFromTextarea(Request $request, string $key, string $description): void
    {
        $raw = $request->input($key, '');
        $items = preg_split('/\r\n|\r|\n/', (string) $raw) ?: [];
        Setting::setList($key, $items, $description);
    }
}
