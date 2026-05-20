<?php

namespace App\Services\BulkImport;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\SupplierInvoice;

class DocumentImportRegistry
{
    public const TYPES = [
        'quotes',
        'purchase-orders',
        'invoices',
        'credit-notes',
        'supplier-invoices',
    ];

    public static function get(string $type): array
    {
        $configs = self::all();

        if (! isset($configs[$type])) {
            throw new \InvalidArgumentException("Type d'import inconnu: {$type}");
        }

        return $configs[$type];
    }

    public static function all(): array
    {
        return [
            'quotes' => [
                'label' => 'Devis',
                'model' => Quote::class,
                'item_relation' => 'items',
                'party_type' => 'client',
                'number_field' => 'quote_number',
                'number_type' => 'devis',
                'redirect_route' => 'quotes.index',
                'template_filename' => 'modele_import_devis.xlsx',
                'columns' => self::mergeColumns(
                    ['reference_import', 'client'],
                    [
                        'date_devis' => ['required' => true, 'field' => 'quote_date'],
                        'date_expiration' => ['field' => 'expiry_date'],
                        'devise' => ['field' => 'currency', 'default' => 'MAD'],
                        'emplacement_stock' => ['field' => 'stock_location', 'default' => 'DEPOT'],
                        'statut' => ['field' => 'status', 'default' => 'brouillon'],
                        'modele' => ['field' => 'model'],
                        'matricule' => ['field' => 'matricule'],
                        'remarques' => ['field' => 'remarks'],
                        'conditions' => ['field' => 'conditions'],
                        'ajustement' => ['field' => 'adjustment', 'default' => 0],
                    ],
                    self::itemColumns()
                ),
                'example' => [
                    'reference_import' => 'DEV-001',
                    'client' => 'Client Exemple',
                    'date_devis' => '2026-01-15',
                    'date_expiration' => '2026-02-15',
                    'devise' => 'MAD',
                    'emplacement_stock' => 'DEPOT',
                    'statut' => 'brouillon',
                    'ref' => 'REF-001',
                    'designation' => 'Produit exemple',
                    'quantite' => 2,
                    'prix_unitaire' => 100,
                    'taux_tva' => 20,
                    'remise' => 0,
                ],
            ],
            'purchase-orders' => [
                'label' => 'Bons de commande',
                'model' => PurchaseOrder::class,
                'item_relation' => 'items',
                'party_type' => 'client',
                'number_field' => 'reference',
                'number_type' => 'bc_client',
                'redirect_route' => 'purchase-orders.index',
                'template_filename' => 'modele_import_bons_commande.xlsx',
                'columns' => self::mergeColumns(
                    ['reference_import', 'client'],
                    [
                        'date_commande' => ['required' => true, 'field' => 'order_date'],
                        'date_expiration' => ['field' => 'expiry_date'],
                        'devise' => ['field' => 'currency', 'default' => 'MAD'],
                        'statut' => ['field' => 'status', 'default' => 'brouillon'],
                        'modele' => ['field' => 'model'],
                        'matricule' => ['field' => 'matricule'],
                        'remarques' => ['field' => 'remarks'],
                        'conditions' => ['field' => 'conditions'],
                        'ajustement' => ['field' => 'adjustment', 'default' => 0],
                    ],
                    self::itemColumns()
                ),
                'example' => [
                    'reference_import' => 'BC-001',
                    'client' => 'Client Exemple',
                    'date_commande' => '2026-01-15',
                    'date_expiration' => '2026-02-15',
                    'devise' => 'MAD',
                    'statut' => 'brouillon',
                    'ref' => 'REF-001',
                    'designation' => 'Produit exemple',
                    'quantite' => 1,
                    'prix_unitaire' => 150,
                    'taux_tva' => 20,
                    'remise' => 0,
                ],
            ],
            'invoices' => [
                'label' => 'Factures',
                'model' => Invoice::class,
                'item_relation' => 'items',
                'party_type' => 'client',
                'number_field' => 'invoice_number',
                'number_type' => 'facture',
                'redirect_route' => 'invoices.index',
                'template_filename' => 'modele_import_factures.xlsx',
                'columns' => self::mergeColumns(
                    ['reference_import', 'client'],
                    [
                        'date_facture' => ['required' => true, 'field' => 'invoice_date'],
                        'date_echeance' => ['field' => 'due_date'],
                        'devise' => ['field' => 'currency', 'default' => 'MAD'],
                        'emplacement_stock' => ['field' => 'stock_location', 'default' => 'DEPOT'],
                        'contact_commercial' => ['field' => 'commercial_contact'],
                        'modele' => ['field' => 'model'],
                        'matricule' => ['field' => 'matricule'],
                        'remarques' => ['field' => 'remarks'],
                        'conditions' => ['field' => 'conditions'],
                        'ajustement' => ['field' => 'adjustment', 'default' => 0],
                    ],
                    self::itemColumns()
                ),
                'example' => [
                    'reference_import' => 'FAC-001',
                    'client' => 'Client Exemple',
                    'date_facture' => '2026-01-15',
                    'date_echeance' => '2026-02-15',
                    'devise' => 'MAD',
                    'emplacement_stock' => 'DEPOT',
                    'ref' => 'REF-001',
                    'designation' => 'Produit exemple',
                    'quantite' => 3,
                    'prix_unitaire' => 200,
                    'taux_tva' => 20,
                    'remise' => 0,
                ],
            ],
            'credit-notes' => [
                'label' => 'Avoirs',
                'model' => CreditNote::class,
                'item_relation' => 'items',
                'party_type' => 'client',
                'number_field' => 'credit_note_number',
                'number_type' => 'avoir',
                'redirect_route' => 'credit-notes.index',
                'template_filename' => 'modele_import_avoirs.xlsx',
                'columns' => self::mergeColumns(
                    ['reference_import', 'client'],
                    [
                        'date_avoir' => ['required' => true, 'field' => 'credit_note_date'],
                        'facture_liee' => ['field' => 'invoice_number'],
                        'devise' => ['field' => 'currency', 'default' => 'MAD'],
                        'emplacement_stock' => ['field' => 'stock_location', 'default' => 'DEPOT'],
                        'remarques' => ['field' => 'remarks'],
                        'conditions' => ['field' => 'conditions'],
                        'ajustement' => ['field' => 'adjustment', 'default' => 0],
                    ],
                    self::itemColumns()
                ),
                'example' => [
                    'reference_import' => 'AV-001',
                    'client' => 'Client Exemple',
                    'date_avoir' => '2026-01-20',
                    'facture_liee' => '',
                    'devise' => 'MAD',
                    'emplacement_stock' => 'DEPOT',
                    'ref' => 'REF-001',
                    'designation' => 'Produit exemple',
                    'quantite' => 1,
                    'prix_unitaire' => 50,
                    'taux_tva' => 20,
                    'remise' => 0,
                ],
            ],
            'supplier-invoices' => [
                'label' => 'Factures fournisseurs',
                'model' => SupplierInvoice::class,
                'item_relation' => 'items',
                'party_type' => 'supplier',
                'number_field' => 'invoice_number',
                'number_type' => 'supplier_invoice',
                'redirect_route' => 'supplier-invoices.index',
                'template_filename' => 'modele_import_factures_fournisseurs.xlsx',
                'columns' => self::mergeColumns(
                    ['reference_import', 'fournisseur'],
                    [
                        'numero_facture' => ['field' => 'invoice_number'],
                        'date_facture' => ['required' => true, 'field' => 'invoice_date'],
                        'date_echeance' => ['field' => 'due_date'],
                        'devise' => ['field' => 'currency', 'default' => 'MAD'],
                        'emplacement_stock' => ['field' => 'stock_location', 'default' => 'DEPOT'],
                        'contact_commercial' => ['field' => 'commercial_contact'],
                        'modele' => ['field' => 'model'],
                        'matricule' => ['field' => 'matricule'],
                        'remarques' => ['field' => 'remarks'],
                        'conditions' => ['field' => 'conditions'],
                        'ajustement' => ['field' => 'adjustment', 'default' => 0],
                    ],
                    self::itemColumns()
                ),
                'example' => [
                    'reference_import' => 'FF-001',
                    'fournisseur' => 'Fournisseur Exemple',
                    'numero_facture' => '',
                    'date_facture' => '2026-01-15',
                    'date_echeance' => '2026-02-15',
                    'devise' => 'MAD',
                    'emplacement_stock' => 'DEPOT',
                    'ref' => 'REF-001',
                    'designation' => 'Produit exemple',
                    'quantite' => 5,
                    'prix_unitaire' => 80,
                    'taux_tva' => 20,
                    'remise' => 0,
                ],
            ],
        ];
    }

    private static function itemColumns(): array
    {
        return [
            'ref' => ['field' => 'ref'],
            'designation' => ['required' => true, 'field' => 'designation'],
            'description' => ['field' => 'description'],
            'quantite' => ['required' => true, 'field' => 'quantity', 'default' => 1],
            'prix_unitaire' => ['required' => true, 'field' => 'unit_price', 'default' => 0],
            'taux_tva' => ['required' => true, 'field' => 'tax_rate', 'default' => 20],
            'remise' => ['field' => 'discount', 'default' => 0],
        ];
    }

    private static function mergeColumns(array $headerKeys, array $headerColumns, array $itemColumns): array
    {
        $columns = [];

        foreach ($headerKeys as $key) {
            $columns[$key] = ['required' => true];
        }

        return array_merge($columns, $headerColumns, $itemColumns);
    }
}
