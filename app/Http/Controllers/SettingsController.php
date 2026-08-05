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
        'bon_livraison', 'bon_livraison_fournisseur', 'bon_reception', 'produit',
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

    /** @var array<string, string> settings_type / tab key => route name */
    protected array $sectionRoutes = [
        'facture' => 'settings.numerotation',
        'devis' => 'settings.numerotation',
        'avoir' => 'settings.numerotation',
        'bc_fournisseur' => 'settings.numerotation',
        'bc_client' => 'settings.numerotation',
        'bon_livraison' => 'settings.numerotation',
        'bon_livraison_fournisseur' => 'settings.numerotation',
        'bon_reception' => 'settings.numerotation',
        'produit' => 'settings.catalogue',
        'produit_types' => 'settings.catalogue',
        'type_produit' => 'settings.catalogue',
        'product_type_categories' => 'settings.catalogue',
        'categorie_tva' => 'settings.fiscalite',
        'vat_categories' => 'settings.fiscalite',
        'depenses' => 'settings.depenses',
        'mon_entreprise' => 'settings.entreprise',
    ];

    public function index()
    {
        return redirect()->route('settings.entreprise');
    }

    public function entreprise()
    {
        return view('settings.entreprise', [
            'settings' => $this->getAllSettings(),
        ]);
    }

    public function numerotation()
    {
        return view('settings.numerotation', [
            'settings' => $this->getAllSettings(),
            'previews' => $this->getPreviewNumbers(),
            'documentSections' => $this->documentSections(),
        ]);
    }

    public function catalogue()
    {
        return view('settings.catalogue', [
            'settings' => $this->getAllSettings(),
        ]);
    }

    public function fiscalite()
    {
        return view('settings.fiscalite', [
            'settings' => $this->getAllSettings(),
        ]);
    }

    public function depenses()
    {
        return view('settings.depenses', [
            'settings' => $this->getAllSettings(),
        ]);
    }

    public function update(Request $request)
    {
        $settingsType = $request->input('settings_type');
        $redirectKey = $settingsType;

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
            $redirectKey = 'produit_types';
        }

        if ($settingsType === 'vat_categories') {
            $this->saveListFromTextarea($request, 'vat_categories', 'Catégories TVA');
            $redirectKey = 'vat_categories';
        }

        if ($settingsType === 'product_type_categories') {
            $this->saveListFromTextarea($request, 'product_type_categories', 'Catégories de type produit');
            $redirectKey = 'product_type_categories';
        }

        if ($request->has('shopify_price_type')) {
            Setting::set(
                'shopify_price_type',
                $request->input('shopify_price_type'),
                'Détermine si les prix des produits Shopify sont TTC ou HT'
            );
            $redirectKey = $redirectKey ?: 'produit';
        }

        $route = $this->sectionRoutes[$redirectKey] ?? 'settings.entreprise';
        $params = [];

        if ($route === 'settings.numerotation' && in_array($settingsType, [
            'facture', 'devis', 'avoir', 'bc_fournisseur', 'bc_client', 'bon_livraison', 'bon_livraison_fournisseur', 'bon_reception',
        ], true)) {
            $params['open'] = $settingsType;
        }

        return redirect()->route($route, $params)->with('success', 'Paramètres mis à jour avec succès.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function documentSections(): array
    {
        $svgDoc = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
        $svgQuote = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>';
        $svgCredit = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l-4-4m0 0l4-4m-4 4h14"/></svg>';
        $svgCart = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>';
        $svgTruck = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M8 17a2 2 0 11-4 0 2 2 0 014 0zm12 0a2 2 0 11-4 0 2 2 0 014 0zM5 17H3v-4m0 0V5a1 1 0 011-1h9l4 5v4m-4 0H5m10 0h2l3 3v3h-3"/></svg>';
        $svgInbox = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>';

        return [
            [
                'key' => 'facture',
                'label' => 'Facture',
                'icon' => $svgDoc,
                'preview_fallback' => 'FA-2026/000001',
                'next_label' => 'Prochain numéro de facture',
                'format_label' => 'Format de numérotation de facture',
                'year_label' => 'Année de facturation (YYYY/000001)',
                'reset_label' => 'Réinitialiser numérotation de facture',
                'remarks_label' => 'Remarques client par défaut',
                'extra' => 'auto_invoice',
            ],
            [
                'key' => 'devis',
                'label' => 'Devis',
                'icon' => $svgQuote,
                'preview_fallback' => 'DV-2026/000001',
                'next_label' => 'Prochain numéro de devis',
                'format_label' => 'Format de numérotation de devis',
                'year_label' => 'Année de devis (YYYY/000001)',
                'reset_label' => 'Réinitialiser numérotation de devis',
                'remarks_label' => 'Remarques client par défaut',
                'extra' => 'validity',
            ],
            [
                'key' => 'avoir',
                'label' => 'Avoir',
                'icon' => $svgCredit,
                'preview_fallback' => 'AV-2026/000001',
                'next_label' => 'Prochain numéro d\'avoir',
                'format_label' => 'Format de numérotation d\'avoir',
                'year_label' => 'Année d\'avoir (YYYY/000001)',
                'reset_label' => 'Réinitialiser numérotation d\'avoir',
                'remarks_label' => 'Remarques client par défaut',
            ],
            [
                'key' => 'bc_fournisseur',
                'label' => 'Bon de Commande Fournisseur',
                'icon' => $svgCart,
                'preview_fallback' => 'BCF-2026/000001',
                'next_label' => 'Prochain numéro de BC fournisseur',
                'format_label' => 'Format de numérotation de BC fournisseur',
                'year_label' => 'Année (YYYY/000001)',
                'reset_label' => 'Réinitialiser numérotation',
                'remarks_label' => 'Remarques fournisseur par défaut',
            ],
            [
                'key' => 'bc_client',
                'label' => 'Bon de Commande Client',
                'icon' => $svgCart,
                'preview_fallback' => 'BC-2026/000001',
                'next_label' => 'Prochain numéro de BC client',
                'format_label' => 'Format de numérotation de BC client',
                'year_label' => 'Année (YYYY/000001)',
                'reset_label' => 'Réinitialiser numérotation',
                'remarks_label' => 'Remarques client par défaut',
            ],
            [
                'key' => 'bon_livraison',
                'label' => 'Bon de Livraison Client',
                'icon' => $svgTruck,
                'preview_fallback' => 'BL-2026/000001',
                'next_label' => 'Prochain numéro de bon de livraison',
                'format_label' => 'Format de numérotation de bon de livraison',
                'year_label' => 'Année (YYYY/000001)',
                'reset_label' => 'Réinitialiser numérotation',
                'remarks_label' => 'Remarques client par défaut',
            ],
            [
                'key' => 'bon_livraison_fournisseur',
                'label' => 'Bon de Livraison Fournisseur',
                'icon' => $svgTruck,
                'preview_fallback' => 'BLF-2026/000001',
                'next_label' => 'Prochain numéro de BL fournisseur',
                'format_label' => 'Format de numérotation de BL fournisseur',
                'year_label' => 'Année (YYYY/000001)',
                'reset_label' => 'Réinitialiser numérotation',
                'remarks_label' => 'Remarques fournisseur par défaut',
            ],
            [
                'key' => 'bon_reception',
                'label' => 'Bon de Réception',
                'icon' => $svgInbox,
                'preview_fallback' => 'BR-2026/000001',
                'next_label' => 'Prochain numéro de bon de réception',
                'format_label' => 'Format de numérotation de bon de réception',
                'year_label' => 'Année (YYYY/000001)',
                'reset_label' => 'Réinitialiser numérotation',
                'remarks_label' => 'Remarques fournisseur par défaut',
            ],
        ];
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
        $settings['company_logo'] = $this->resolvePublicSetting('company_logo', 'Logo entreprise');
        $settings['company_cachet'] = $this->resolvePublicSetting('company_cachet', 'Cachet entreprise');

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

    protected function resolvePublicSetting(string $key, string $description): ?string
    {
        $path = Setting::get($key);
        if (! $path) {
            return null;
        }

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        Setting::set($key, null, $description);

        return null;
    }
}
