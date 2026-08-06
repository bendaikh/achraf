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
                    ['label' => 'Déclarations', 'route' => 'financial.declarations', 'active' => ['financial.declarations', 'financial.declarations.*', 'financial.export']],
                ],
            ],
            [
                'label' => 'Gestion achats',
                'route' => 'expenses-with-invoice.index',
                'key' => 'purchases',
                'soft_nav' => true,
                'active_paths' => ['purchases/*'],
                'icon' => ['M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
                'children' => [
                    ['label' => 'Dépense avec facture', 'route' => 'expenses-with-invoice.index', 'active' => ['expenses-with-invoice.*']],
                    ['label' => 'Dépense sans facture', 'route' => 'expenses-without-invoice.index', 'active' => ['expenses-without-invoice.*']],
                    ['label' => 'BC Fournisseur', 'route' => 'supplier-purchase-orders.index', 'active' => ['supplier-purchase-orders.*']],
                    ['label' => 'Bon de livraison', 'route' => 'supplier-delivery-notes.index', 'active' => ['supplier-delivery-notes.*']],
                    ['label' => 'Bon de réception', 'route' => 'receptions.index', 'active' => ['receptions.*']],
                    ['label' => 'Factures fournisseur', 'route' => 'supplier-invoices.index', 'active' => ['supplier-invoices.*']],
                    ['label' => 'Avoirs fournisseur', 'route' => 'supplier-credit-notes.index', 'active' => ['supplier-credit-notes.*']],
                    ['label' => 'Gestion Paiement', 'route' => 'purchases.payments.index', 'active' => ['purchases.payments.*', 'supplier-invoices.payments.*']],
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
                'label' => 'CRM',
                'route' => 'clients.index',
                'key' => 'crm',
                'soft_nav' => true,
                'active_paths' => ['crm/*'],
                'icon' => ['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                'children' => [
                    ['label' => 'Clients', 'route' => 'clients.index', 'active' => ['clients.*']],
                    ['label' => 'Fournisseurs', 'route' => 'suppliers.index', 'active' => ['suppliers.*']],
                ],
            ],
            [
                'label' => 'Gestion produits',
                'route' => 'products.index',
                'key' => 'products',
                'soft_nav' => true,
                'active' => ['products.*'],
                'icon' => ['M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ],
            [
                'label' => 'Gestion stock',
                'route' => 'stock.enligne.index',
                'key' => 'stock',
                'soft_nav' => true,
                'active_paths' => ['stock/*'],
                'icon' => ['M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4'],
                'children' => [
                    ['label' => 'Stock Enligne', 'route' => 'stock.enligne.index', 'active' => ['stock.enligne.*']],
                    ['label' => 'Stock Magasin', 'route' => 'stock.magasin.index', 'active' => ['stock.magasin.*']],
                ],
            ],
            [
                'label' => 'Gestion ventes',
                'route' => 'orders.index',
                'key' => 'sales',
                'soft_nav' => true,
                'active_paths' => ['sales/*'],
                'icon' => ['M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                'children' => [
                    ['label' => 'Commandes', 'route' => 'orders.index', 'active' => ['orders.*']],
                    ['label' => 'Devis', 'route' => 'quotes.index', 'active' => ['quotes.*']],
                    ['label' => 'BC', 'route' => 'purchase-orders.index', 'active' => ['purchase-orders.*']],
                    ['label' => 'Bon de livraison', 'route' => 'delivery-notes.index', 'active' => ['delivery-notes.*']],
                    ['label' => 'Factures', 'route' => 'invoices.index', 'active' => ['invoices.*']],
                    ['label' => 'Avoirs', 'route' => 'credit-notes.index', 'active' => ['credit-notes.*']],
                    ['label' => 'Gestion Paiement', 'route' => 'sales.payments.index', 'active' => ['sales.payments.*', 'invoices.payments.*']],
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

        return ($routePatterns !== [] && $request->routeIs(...$routePatterns))
            || ($pathPatterns !== [] && $request->is(...$pathPatterns));
    }

    /**
     * @param array<int, array<string, mixed>> $modules
     * @return array<string, mixed>|null
     */
    public static function activeModule(array $modules, Request $request): ?array
    {
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

        if (isset($item['roles'])) {
            return $user->isSuperAdmin()
                || collect($item['roles'])->contains(fn (string $role): bool => $user->hasRole($role));
        }

        return true;
    }
}
