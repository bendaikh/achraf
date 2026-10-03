<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class Navigation
{
    /**
     * Return the navigation modules visible to the current user.
     *
     * Items may define a "can" ability or a "roles" list. The current
     * navigation does not restrict any item, so this preserves existing access
     * while keeping the menu ready for route-level permissions.
     *
     * soft_nav defaults to true so section switches keep the app shell mounted.
     * Set soft_nav => false to force a full page load for a module.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function modules(?User $user = null): array
    {
        $modules = [
            [
                'label' => 'Tableau de bord',
                'route' => 'dashboard',
                'key' => 'dashboard',
                'soft_nav' => true,
                'active' => ['dashboard', 'dashboard.*'],
                'icon' => ['M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
            ],
            [
                'label' => 'Gestion Financière',
                'route' => 'financial.index',
                'key' => 'financial',
                'soft_nav' => true,
                'active' => ['financial.*'],
                'icon' => ['M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                'children' => [
                    ['label' => 'Vue d\'ensemble', 'route' => 'financial.index', 'active' => ['financial.index']],
                    ['label' => 'TVA', 'route' => 'financial.tva', 'active' => ['financial.tva', 'financial.tva.*']],
                    ['label' => 'Trésorerie', 'route' => 'financial.tresorerie', 'active' => ['financial.tresorerie', 'financial.tresorerie.*']],
                    ['label' => 'Achats & dépenses', 'route' => 'financial.achats-depenses', 'active' => ['financial.achats-depenses']],
                    ['label' => 'Créances & dettes', 'route' => 'financial.creances-dettes', 'active' => ['financial.creances-dettes', 'financial.creances-dettes.*']],
                    ['label' => 'Mouvements', 'route' => 'financial.mouvements.index', 'active' => ['financial.mouvements.*']],
                    ['label' => 'Dotations', 'route' => 'financial.endowments.index', 'active' => ['financial.endowments.*']],
                    ['label' => 'Cartes', 'route' => 'financial.cards.index', 'active' => ['financial.cards.*']],
                    ['label' => 'Bascule / soldes', 'route' => 'financial.cutoff.index', 'active' => ['financial.cutoff.*']],
                    ['label' => 'Relevés bancaires', 'route' => 'financial.bank-imports.index', 'active' => ['financial.bank-imports.*']],
                    ['label' => 'Déclarations', 'route' => 'financial.declarations', 'active' => ['financial.declarations', 'financial.declarations.*', 'financial.export']],
                ],
            ],
            [
                'label' => 'Gestion achats',
                'route' => 'expenses-with-invoice.index',
                'key' => 'purchases',
                'soft_nav' => true,
                'active_paths' => ['purchases/*', 'documents/archive*'],
                'icon' => ['M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
                'children' => [
                    ['label' => 'Dépense avec facture', 'route' => 'expenses-with-invoice.index', 'active' => ['expenses-with-invoice.*'], 'group' => 'Dépenses'],
                    ['label' => 'Dépense sans facture', 'route' => 'expenses-without-invoice.index', 'active' => ['expenses-without-invoice.*'], 'group' => 'Dépenses'],
                    ['label' => 'Besoins d\'achat', 'route' => 'purchases.needs.index', 'active' => ['purchases.needs.*'], 'group' => 'Achats / Fournisseurs'],
                    ['label' => 'BC fournisseur', 'route' => 'supplier-purchase-orders.index', 'active' => ['supplier-purchase-orders.*'], 'group' => 'Achats / Fournisseurs'],
                    ['label' => 'Bon de livraison fournisseur', 'route' => 'supplier-delivery-notes.index', 'active' => ['supplier-delivery-notes.*'], 'group' => 'Achats / Fournisseurs'],
                    ['label' => 'Bon de réception', 'route' => 'receptions.index', 'active' => ['receptions.*'], 'group' => 'Achats / Fournisseurs'],
                    ['label' => 'Factures fournisseur', 'route' => 'supplier-invoices.index', 'active' => ['supplier-invoices.index', 'supplier-invoices.create', 'supplier-invoices.store', 'supplier-invoices.show', 'supplier-invoices.edit', 'supplier-invoices.update', 'supplier-invoices.destroy', 'supplier-invoices.print', 'supplier-invoices.pdf', 'supplier-invoices.by-supplier', 'supplier-invoices.import', 'supplier-invoices.import.*', 'supplier-invoices.receive-stock'], 'group' => 'Achats / Fournisseurs'],
                    ['label' => 'Avoirs fournisseur', 'route' => 'supplier-credit-notes.index', 'active' => ['supplier-credit-notes.*'], 'group' => 'Achats / Fournisseurs'],
                    ['label' => 'Gestion paiement', 'route' => 'purchases.payments.index', 'active' => ['purchases.payments.*', 'supplier-invoices.payments.*'], 'group' => 'Finance'],
                    ['label' => 'Export documents comptables', 'route' => 'documents.archive.index', 'active' => ['documents.archive.*'], 'group' => 'Finance'],
                ],
            ],
            [
                'label' => 'Point de vente',
                'route' => 'pos.index',
                'key' => 'pos',
                'soft_nav' => true,
                'active' => ['pos.*'],
                'icon' => ['M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
                'children' => [
                    ['label' => 'Caisse', 'route' => 'pos.index', 'active' => ['pos.index']],
                    ['label' => 'Historique & paiements', 'route' => 'pos.sales.index', 'active' => ['pos.sales.*']],
                ],
            ],
            [
                'label' => 'CRM / Équipe commerciale',
                'route' => 'clients.index',
                'key' => 'crm',
                'soft_nav' => true,
                'active' => ['access.commissions.*', 'access.dashboard.commercial', 'access.dashboard.team'],
                'active_paths' => ['crm/*'],
                'icon' => ['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                'children' => [
                    ['label' => 'Clients', 'route' => 'clients.index', 'active' => ['clients.*'], 'group' => 'Clients & fournisseurs'],
                    ['label' => 'Fournisseurs', 'route' => 'suppliers.index', 'active' => ['suppliers.*'], 'group' => 'Clients & fournisseurs'],
                    ['label' => 'Commissions', 'route' => 'access.commissions.index', 'active' => ['access.commissions.*'], 'group' => 'Équipe commerciale'],
                    ['label' => 'Mon tableau commercial', 'route' => 'access.dashboard.commercial', 'active' => ['access.dashboard.commercial'], 'group' => 'Équipe commerciale'],
                    ['label' => 'Équipe commerciale', 'route' => 'access.dashboard.team', 'active' => ['access.dashboard.team'], 'roles' => ['superadmin', 'admin', 'administrateur', 'responsable-commercial'], 'group' => 'Équipe commerciale'],
                ],
            ],
            [
                'label' => 'RH',
                'route' => 'hr.dashboard',
                'key' => 'hr',
                'soft_nav' => true,
                'active' => ['hr.*'],
                'icon' => ['M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                'children' => [
                    ['label' => 'Tableau de bord RH', 'route' => 'hr.dashboard', 'active' => ['hr.dashboard']],
                    ['label' => 'Salariés', 'route' => 'hr.employees.index', 'active' => ['hr.employees.*']],
                    ['label' => 'Contrats', 'route' => 'hr.contracts.index', 'active' => ['hr.contracts.*']],
                    ['label' => 'Présences & Pointage', 'route' => 'hr.attendance.index', 'active' => ['hr.attendance.*']],
                    ['label' => 'Congés & Absences', 'route' => 'hr.leaves.index', 'active' => ['hr.leaves.*']],
                    ['label' => 'Rémunération / Paie', 'route' => 'hr.payroll.index', 'active' => ['hr.payroll.*'], 'hr_can' => 'view_salaries'],
                    ['label' => 'Primes & Indemnités', 'route' => 'hr.compensations.index', 'active' => ['hr.compensations.*'], 'hr_can' => 'view_salaries'],
                    ['label' => 'Documents RH', 'route' => 'hr.documents.index', 'active' => ['hr.documents.*'], 'hr_can' => 'view_hr_documents'],
                    ['label' => 'Rapports RH', 'route' => 'hr.reports.index', 'active' => ['hr.reports.*']],
                    ['label' => 'Historique RH', 'route' => 'hr.history.index', 'active' => ['hr.history.*']],
                    ['label' => 'Paramètres RH', 'route' => 'hr.settings.index', 'active' => ['hr.settings.*'], 'hr_can' => 'manage_hr_settings'],
                ],
            ],
            [
                'label' => 'Gestion produits',
                'route' => 'products.index',
                'key' => 'products',
                'soft_nav' => true,
                'active' => ['products.*'],
                'active_paths' => ['stock/*'],
                'icon' => ['M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                'children' => [
                    ['label' => 'Liste des produits', 'route' => 'products.index', 'list_reset' => true, 'active' => ['products.index', 'products.show', 'products.edit']],
                    ['label' => 'Ajouter un produit / service', 'route' => 'products.create', 'active' => ['products.create']],
                    ['label' => 'Services', 'route' => 'products.index', 'route_params' => ['item_kind' => 'service'], 'active' => ['products.index']],
                    ['label' => 'Catégories', 'route' => 'products.categories', 'active' => ['products.categories']],
                    ['label' => 'Inventaire', 'route' => 'stock.inventory.index', 'active' => ['stock.inventory.*', 'stock.magasin.*', 'stock.enligne.*']],
                    ['label' => 'Stock par emplacement', 'route' => 'stock.locations.index', 'active' => ['stock.locations.*']],
                    ['label' => 'À approvisionner', 'route' => 'stock.replenishment.index', 'active' => ['stock.replenishment.*']],
                    ['label' => 'Alertes stock', 'route' => 'stock.alerts.index', 'active' => ['stock.alerts.*']],
                    ['label' => 'Mouvements de stock', 'route' => 'stock.movements.index', 'active' => ['stock.movements.*', 'stock.transfer.*']],
                ],
            ],
            [
                'label' => 'Gestion ventes',
                'route' => 'orders.index',
                'key' => 'sales',
                'soft_nav' => true,
                // Fallback for sales/* pages not listed below; a more specific module
                // (Gestion financière clients) wins when one of its tabs matches.
                'active_paths' => ['sales/*'],
                'icon' => ['M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                'children' => [
                    ['label' => 'Commandes', 'route' => 'orders.index', 'active' => ['orders.*']],
                    ['label' => 'Préparation / Picking', 'route' => 'sales.picking.index', 'active' => ['sales.picking.*']],
                    ['label' => 'Retours clients / Scan', 'route' => 'sales.returns.index', 'active' => ['sales.returns.*']],
                    ['label' => 'Devis', 'route' => 'quotes.index', 'active' => ['quotes.*']],
                    ['label' => 'Bons de commande', 'route' => 'purchase-orders.index', 'active' => ['purchase-orders.*']],
                    ['label' => 'Bons de livraison', 'route' => 'delivery-notes.index', 'active' => ['delivery-notes.*']],
                    [
                        'label' => 'Factures clients',
                        'route' => 'invoices.index',
                        // invoices.payments.* belongs to Gestion financière clients.
                        'active' => [
                            'invoices.index', 'invoices.create', 'invoices.store', 'invoices.show', 'invoices.edit',
                            'invoices.update', 'invoices.destroy', 'invoices.print', 'invoices.pdf', 'invoices.by-client',
                            'invoices.import', 'invoices.import.*', 'invoices.payment-status',
                        ],
                    ],
                    ['label' => 'Avoirs clients', 'route' => 'credit-notes.index', 'active' => ['credit-notes.*', 'sales.refunds.*']],
                ],
            ],
            [
                'label' => 'Gestion financière clients',
                'route' => 'sales.payments.index',
                'key' => 'sales-finance',
                'soft_nav' => true,
                'active' => ['sales.payments.*', 'invoices.payments.*', 'sales-finance.*'],
                'icon' => ['M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
                'children' => [
                    ['label' => 'Gestion des paiements clients', 'route' => 'sales.payments.index', 'active' => ['sales.payments.*', 'invoices.payments.*']],
                    ['label' => 'Encaissements', 'route' => 'sales-finance.receipts', 'active' => ['sales-finance.receipts']],
                    ['label' => 'Impayés / reste à payer', 'route' => 'sales-finance.unpaid', 'active' => ['sales-finance.unpaid']],
                    ['label' => 'Rapprochements / trésorerie', 'route' => 'sales-finance.reconciliation', 'active' => ['sales-finance.reconciliation']],
                ],
            ],
            [
                'label' => 'Intégrations',
                'route' => 'integrations.shopify.edit',
                'key' => 'integrations',
                'soft_nav' => true,
                'active' => ['integrations.*'],
                'icon' => ['M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z'],
                'children' => [
                    ['label' => 'Shopify', 'route' => 'integrations.shopify.edit', 'active' => ['integrations.shopify.*']],
                    ['label' => 'Jumia', 'route' => 'integrations.jumia.edit', 'active' => ['integrations.jumia.*']],
                ],
            ],
            [
                'label' => 'Administration',
                'route' => 'access.collaborators.index',
                'key' => 'access',
                'soft_nav' => true,
                'active' => ['access.*'],
                'roles' => ['superadmin', 'admin', 'administrateur'],
                'icon' => ['M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
                'children' => [
                    ['label' => 'Collaborateurs', 'route' => 'access.collaborators.index', 'active' => ['access.collaborators.*'], 'roles' => ['superadmin', 'admin', 'administrateur']],
                    ['label' => 'Utilisateurs', 'route' => 'access.users.index', 'active' => ['access.users.*'], 'roles' => ['superadmin', 'admin', 'administrateur']],
                    ['label' => 'Rôles & permissions', 'route' => 'access.roles.index', 'active' => ['access.roles.*'], 'roles' => ['superadmin', 'admin', 'administrateur']],
                    ['label' => 'Journal d\'activité', 'route' => 'access.activity.index', 'active' => ['access.activity.*'], 'roles' => ['superadmin', 'admin', 'administrateur']],
                    ['label' => 'Commissions', 'route' => 'access.commissions.index', 'active' => ['access.commissions.*'], 'roles' => ['superadmin', 'admin', 'administrateur']],
                ],
            ],
            [
                'label' => 'Paramètres',
                'route' => 'settings.entreprise',
                'key' => 'settings',
                'soft_nav' => true,
                'active' => ['settings.*'],
                'icon' => [
                    'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
                    'M15 12a3 3 0 11-6 0 3 3 0 016 0z',
                ],
                'children' => [
                    ['label' => 'Mon Entreprise', 'route' => 'settings.entreprise', 'active' => ['settings.entreprise']],
                    ['label' => 'Numérotation', 'route' => 'settings.numerotation', 'active' => ['settings.numerotation']],
                    ['label' => 'Catalogue', 'route' => 'settings.catalogue', 'active' => ['settings.catalogue']],
                    ['label' => 'Fiscalité', 'route' => 'settings.fiscalite', 'active' => ['settings.fiscalite']],
                    ['label' => 'Dépenses', 'route' => 'settings.depenses', 'active' => ['settings.depenses']],
                    ['label' => 'Stock & Logistique', 'route' => 'settings.stock', 'active' => ['settings.stock', 'warehouses.*']],
                    ['label' => 'E-mail / SMTP', 'route' => 'settings.smtp', 'active' => ['settings.smtp']],
                ],
            ],
        ];

        return array_values(array_filter(array_map(
            function (array $module) use ($user): ?array {
                if (! self::allowed($module, $user)) {
                    return null;
                }

                if (isset($module['children'])) {
                    $module['children'] = array_values(array_filter(
                        $module['children'],
                        fn (array $item): bool => self::allowed($item, $user)
                    ));

                    if ($module['children'] === []) {
                        return null;
                    }

                    $module['route'] = $module['children'][0]['route'];
                }

                return $module;
            },
            $modules
        )));
    }

    /**
     * @param array<string, mixed> $item
     */
    public static function isActive(array $item, Request $request): bool
    {
        $routePatterns = $item['active'] ?? [];
        $pathPatterns = $item['active_paths'] ?? [];

        $routeMatches = ($routePatterns !== [] && $request->routeIs(...$routePatterns))
            || ($pathPatterns !== [] && $request->is(...$pathPatterns));

        if (! $routeMatches && $routePatterns === [] && $pathPatterns === [] && isset($item['route_params'])) {
            $routeMatches = $request->routeIs($item['route']);
        }

        if (! $routeMatches) {
            return false;
        }

        if (! empty($item['route_params']) && is_array($item['route_params'])) {
            foreach ($item['route_params'] as $key => $value) {
                if ((string) $request->query($key) !== (string) $value) {
                    return false;
                }
            }

            return true;
        }

        // Tabs without query params should not stay active when a sibling query filter is set.
        // Only apply to leaf nav items (no children), never to parent modules.
        if (
            empty($item['children'])
            && ($item['route'] ?? null) === 'products.index'
            && empty($item['route_params'])
        ) {
            // "Liste des produits" stays active for type/source filters,
            // except the dedicated "Services" nav tab (item_kind=service only).
            if ((string) $request->query('item_kind') === 'service'
                && ! $request->filled('stock_status')
                && count(array_filter($request->query())) <= 2
            ) {
                // Allow Services sibling to win when only item_kind=service is set.
                $otherQuery = collect($request->query())->except(['item_kind', 'page'])->filter(fn ($v) => $v !== null && $v !== '');
                if ($otherQuery->isEmpty()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function url(array $item): string
    {
        $params = $item['route_params'] ?? [];

        return route($item['route'], $params);
    }

    /**
     * @param array<int, array<string, mixed>> $modules
     * @return array<string, mixed>|null
     */
    public static function activeModule(array $modules, Request $request): ?array
    {
        // 1) A module owning an active tab wins (e.g. sales/payments belongs to
        //    « Gestion financière clients » even though Gestion ventes matches sales/*).
        foreach ($modules as $module) {
            foreach ($module['children'] ?? [] as $child) {
                if (self::isActive($child, $request)) {
                    return $module;
                }
            }
        }

        // 2) Fallback: module-level route / path patterns.
        foreach ($modules as $module) {
            if (self::isActive($module, $request)) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function allowed(array $item, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (isset($item['can']) && ! Gate::forUser($user)->allows($item['can'])) {
            return false;
        }

        if (isset($item['hr_can']) && ! \App\Support\HrPermission::allows($user, $item['hr_can'])) {
            return false;
        }

        if (isset($item['roles'])) {
            return $user->isSuperAdmin()
                || collect($item['roles'])->contains(fn (string $role): bool => $user->hasRole($role));
        }

        return true;
    }
}
