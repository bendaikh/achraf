<?php

namespace App\Support;

/**
 * Catalogue des permissions Libromart.
 * Les clés sont seedées en base et servent de modèle extensible.
 */
class PermissionCatalog
{
    public const MODULES = [
        'ventes' => 'Ventes',
        'achats' => 'Achats',
        'stock' => 'Stock',
        'tiers' => 'Tiers',
        'finance' => 'Finance',
        'rh' => 'RH',
        'administration' => 'Administration',
        'sensible' => 'Permissions sensibles',
    ];

    public const ACTIONS = [
        'voir' => 'Voir',
        'creer' => 'Créer',
        'modifier' => 'Modifier',
        'valider' => 'Valider',
        'annuler' => 'Annuler',
        'supprimer' => 'Supprimer',
        'exporter' => 'Exporter',
    ];

    public const DATA_SCOPES = [
        'own' => 'Mes données uniquement',
        'team' => 'Mon équipe',
        'warehouses' => 'Dépôts attribués',
        'all' => 'Toutes les données',
    ];

    /**
     * @return list<array{key: string, module: string, resource: ?string, action: ?string, label: string, group_label: ?string, is_sensitive: bool, sort_order: int}>
     */
    public static function all(): array
    {
        $items = [];
        $order = 0;

        foreach (self::matrix() as $module => $resources) {
            foreach ($resources as $resource => $label) {
                foreach (self::ACTIONS as $action => $actionLabel) {
                    $order += 10;
                    $items[] = [
                        'key' => "{$module}.{$resource}.{$action}",
                        'module' => $module,
                        'resource' => $resource,
                        'action' => $action,
                        'label' => "{$label} — {$actionLabel}",
                        'group_label' => $label,
                        'is_sensitive' => false,
                        'sort_order' => $order,
                    ];
                }
            }
        }

        foreach (self::sensitive() as $key => $meta) {
            $order += 10;
            $items[] = [
                'key' => $key,
                'module' => 'sensible',
                'resource' => $meta['resource'] ?? null,
                'action' => $meta['action'] ?? null,
                'label' => $meta['label'],
                'group_label' => $meta['group'] ?? 'Sensibles',
                'is_sensitive' => true,
                'sort_order' => $order,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function matrix(): array
    {
        return [
            'ventes' => [
                'devis' => 'Devis',
                'bc_client' => 'BC client',
                'bl_client' => 'BL client',
                'factures' => 'Factures',
                'avoirs' => 'Avoirs',
                'paiements' => 'Paiements',
                'commissions' => 'Commissions',
            ],
            'achats' => [
                'bc_fournisseur' => 'BC fournisseur',
                'bl_fournisseur' => 'BL fournisseur',
                'bon_reception' => 'Bon de réception',
                'factures_fournisseurs' => 'Factures fournisseurs',
                'avoirs_fournisseurs' => 'Avoirs fournisseurs',
                'paiements' => 'Paiements fournisseurs',
                'depenses' => 'Dépenses',
            ],
            'stock' => [
                'produits' => 'Produits',
                'depots' => 'Dépôts',
                'emplacements' => 'Emplacements',
                'mouvements' => 'Mouvements',
                'transferts' => 'Transferts',
                'inventaires' => 'Inventaires',
                'ajustements' => 'Ajustements',
            ],
            'tiers' => [
                'clients' => 'Clients',
                'fournisseurs' => 'Fournisseurs',
            ],
            'finance' => [
                'caisse' => 'Caisse',
                'tresorerie' => 'Trésorerie',
                'exports' => 'Exports comptables',
            ],
            'rh' => [
                'salaries' => 'Salariés',
                'pointage' => 'Pointage',
                'absences' => 'Absences',
                'primes' => 'Primes',
                'paie' => 'Paie',
                'commissions' => 'Commissions RH',
            ],
            'administration' => [
                'utilisateurs' => 'Utilisateurs',
                'roles' => 'Rôles',
                'collaborateurs' => 'Collaborateurs',
                'parametres' => 'Paramètres',
                'rapports' => 'Rapports',
                'imports_exports' => 'Imports / Exports',
                'journal' => 'Journal d\'activité',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, group?: string, resource?: string, action?: string}>
     */
    public static function sensitive(): array
    {
        return [
            'sensible.voir_prix_achat' => ['label' => 'Voir prix d\'achat', 'group' => 'Prix & marges'],
            'sensible.voir_cout_revient' => ['label' => 'Voir coût de revient', 'group' => 'Prix & marges'],
            'sensible.voir_marge' => ['label' => 'Voir marge', 'group' => 'Prix & marges'],
            'sensible.voir_benefice' => ['label' => 'Voir bénéfice', 'group' => 'Prix & marges'],
            'sensible.modifier_prix_vente' => ['label' => 'Modifier prix de vente', 'group' => 'Prix & marges'],
            'sensible.modifier_prix_achat' => ['label' => 'Modifier prix d\'achat', 'group' => 'Prix & marges'],
            'sensible.accorder_remise' => ['label' => 'Accorder remise', 'group' => 'Prix & marges'],
            'sensible.depasser_remise_max' => ['label' => 'Dépasser remise maximale', 'group' => 'Prix & marges'],
            'sensible.voir_caisse' => ['label' => 'Voir caisse', 'group' => 'Paiements'],
            'sensible.enregistrer_paiement' => ['label' => 'Enregistrer paiement', 'group' => 'Paiements'],
            'sensible.annuler_paiement' => ['label' => 'Annuler paiement', 'group' => 'Paiements'],
            'sensible.ajuster_stock' => ['label' => 'Ajuster stock', 'group' => 'Stock'],
            'sensible.voir_toutes_ventes' => ['label' => 'Voir toutes les ventes', 'group' => 'Ventes'],
            'sensible.voir_uniquement_ses_ventes' => ['label' => 'Voir uniquement ses ventes', 'group' => 'Ventes'],
            'sensible.voir_salaires' => ['label' => 'Voir salaires', 'group' => 'RH'],
            'sensible.voir_commissions' => ['label' => 'Voir commissions', 'group' => 'Commissions'],
            'sensible.modifier_commissions' => ['label' => 'Modifier commissions', 'group' => 'Commissions'],
            'sensible.valider_commissions' => ['label' => 'Valider commissions', 'group' => 'Commissions'],
            'sensible.reattribuer_commercial' => ['label' => 'Réattribuer commercial', 'group' => 'Ventes'],
            'sensible.modifier_document_valide' => ['label' => 'Modifier document validé', 'group' => 'Documents'],
            'sensible.annuler_document_valide' => ['label' => 'Annuler document validé', 'group' => 'Documents'],
            'sensible.exporter_donnees' => ['label' => 'Exporter données', 'group' => 'Administration'],
            'sensible.gerer_utilisateurs' => ['label' => 'Gérer utilisateurs', 'group' => 'Administration'],
            'sensible.gerer_permissions' => ['label' => 'Gérer permissions', 'group' => 'Administration'],
            'sensible.acceder_parametres' => ['label' => 'Accéder aux paramètres', 'group' => 'Administration'],
        ];
    }

    /**
     * Default permission keys per role slug (templates — customizable per user afterwards).
     *
     * @return array<string, list<string>|string>
     */
    public static function roleTemplates(): array
    {
        return [
            'superadmin' => '*',
            'administrateur' => '*',
            'admin' => '*', // legacy slug
            'comptable' => [
                'ventes.factures.*',
                'ventes.avoirs.*',
                'ventes.paiements.*',
                'achats.factures_fournisseurs.*',
                'achats.avoirs_fournisseurs.*',
                'achats.paiements.*',
                'achats.depenses.*',
                'finance.*',
                'sensible.voir_caisse',
                'sensible.enregistrer_paiement',
                'sensible.annuler_paiement',
                'sensible.exporter_donnees',
            ],
            'responsable-commercial' => [
                'ventes.*',
                'tiers.clients.*',
                'sensible.voir_marge',
                'sensible.voir_commissions',
                'sensible.valider_commissions',
                'sensible.reattribuer_commercial',
                'sensible.voir_toutes_ventes',
                'sensible.accorder_remise',
            ],
            'commercial' => [
                'ventes.devis.*',
                'ventes.bc_client.*',
                'ventes.bl_client.voir',
                'ventes.factures.voir',
                'ventes.paiements.voir',
                'ventes.commissions.voir',
                'tiers.clients.*',
                'sensible.voir_uniquement_ses_ventes',
                'sensible.voir_commissions',
                'sensible.accorder_remise',
            ],
            'magasinier' => [
                'stock.*',
                'achats.bl_fournisseur.voir',
                'achats.bon_reception.*',
                'ventes.bl_client.*',
            ],
            'user' => [
                'ventes.devis.voir',
                'tiers.clients.voir',
            ],
        ];
    }

    /**
     * Expand patterns like "ventes.*" or "ventes.devis.*" against a list of keys.
     *
     * @param  list<string>  $patterns
     * @param  list<string>  $allKeys
     * @return list<string>
     */
    public static function expandPatterns(array $patterns, array $allKeys): array
    {
        if (in_array('*', $patterns, true)) {
            return $allKeys;
        }

        $matched = [];
        foreach ($patterns as $pattern) {
            if (! str_contains($pattern, '*')) {
                if (in_array($pattern, $allKeys, true)) {
                    $matched[] = $pattern;
                }
                continue;
            }

            $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/';
            foreach ($allKeys as $key) {
                if (preg_match($regex, $key)) {
                    $matched[] = $key;
                }
            }
        }

        return array_values(array_unique($matched));
    }
}
