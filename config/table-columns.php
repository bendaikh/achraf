<?php

/**
 * Libromart — définitions globales des colonnes de tableaux.
 *
 * Chaque tableau possède un identifiant unique (table_key).
 * Les clés de colonnes doivent correspondre aux classes lm-col-{key} dans les vues.
 */

$select = ['key' => 'select', 'label' => 'Sélection', 'default' => true, 'locked' => true, 'optional' => false];
$actions = ['key' => 'actions', 'label' => 'Actions', 'default' => true, 'locked' => true, 'optional' => false];

$col = static fn (string $key, string $label, bool $default = true, bool $optional = false, bool $locked = false, ?string $exportField = null) => [
    'key' => $key,
    'label' => $label,
    'default' => $default,
    'locked' => $locked,
    'optional' => $optional,
    'export_field' => $exportField,
];

return [

    // ── Ventes ──────────────────────────────────────────────────────────

    'orders' => [
        'label' => 'Commandes',
        'columns' => [
            $select,
            $col('numero', 'N° Commande', true, false, true),
            $col('source', 'Source'),
            $col('client', 'Client'),
            $col('date', 'Date'),
            $col('total', 'Total'),
            $col('paiement', 'Paiement'),
            $col('livraison', 'Livraison'),
            $col('external_id', 'ID externe', false, true),
            $col('shopify_id', 'Shopify ID', false, true),
            $col('created_at', 'Date création', false, true),
            $actions,
        ],
    ],

    'quotes' => [
        'label' => 'Devis',
        'columns' => [
            $select,
            $col('numero', 'Numéro', true, false, true),
            $col('client', 'Client'),
            $col('date', 'Date'),
            $col('validite', 'Échéance', false, true),
            $col('devise', 'Devise', false, true),
            $col('total', 'Total'),
            $actions,
        ],
    ],

    'purchase-orders' => [
        'label' => 'Bons de commande client',
        'columns' => [
            $select,
            $col('numero', 'Numéro', true, false, true),
            $col('client', 'Client'),
            $col('date', 'Date'),
            $col('echeance', 'Échéance', false, true),
            $col('devise', 'Devise', false, true),
            $col('total', 'Total'),
            $actions,
        ],
    ],

    'delivery-notes' => [
        'label' => 'Bons de livraison',
        'columns' => [
            $select,
            $col('numero', 'Numéro', true, false, true),
            $col('client', 'Client'),
            $col('date', 'Date'),
            $col('date_expedition', 'Date expédition', false, true),
            $col('statut', 'Statut'),
            $col('total', 'Total'),
            $col('bl_genere', 'BL généré', false, true),
            $col('bl_signe', 'BL signé', false, true),
            $actions,
        ],
    ],

    'invoices' => [
        'label' => 'Factures clients',
        'columns' => [
            $select,
            $col('numero', 'Numéro', true, false, true, 'invoice_number'),
            $col('client', 'Client', true, false, false, 'client.name'),
            $col('commande', 'N° commande'),
            $col('origine', 'Source', false, true),
            $col('statut_commercial', 'Statut commercial'),
            $col('date', 'Date', true, false, false, 'invoice_date'),
            $col('echeance', 'Échéance', false, true),
            $col('devise', 'Devise', false, true, false, 'currency'),
            $col('total', 'Montant initial', true, false, false, 'total'),
            $col('avoirs', 'Avoirs'),
            $col('net', 'Net'),
            $col('document', 'Document généré', false, true),
            $col('paiement', 'Statut paiement', false, true),
            $actions,
        ],
    ],

    'credit-notes' => [
        'label' => 'Avoirs clients',
        'columns' => [
            $select,
            $col('numero', 'Numéro', true, false, true),
            $col('client', 'Client'),
            $col('facture', 'Facture'),
            $col('date', 'Date'),
            $col('devise', 'Devise', false, true),
            $col('total', 'Total'),
            $col('document', 'Reçu importé'),
            $actions,
        ],
    ],

    'sales-payments' => [
        'label' => 'Gestion paiement ventes',
        'columns' => [
            $select,
            $col('facture', 'N° facture', true, false, true, 'invoice_number'),
            $col('commande', 'N° commande', false, true),
            $col('tracking', 'Tracking', false, true),
            $col('client', 'Client', true, false, false, 'client.name'),
            $col('date', 'Date', true, false, false, 'invoice_date'),
            $col('echeance', 'Échéance', false, true),
            $col('total', 'Total', true, false, false, 'total'),
            $col('deja_paye', 'Encaissé'),
            $col('solde', 'Solde'),
            $col('montant_fichier', 'Montant fichier', false, true),
            $col('frais', 'Frais', false, true),
            $col('ecart', 'Écart', false, true),
            $col('mode', 'Mode de paiement', false, true),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'sales-refunds' => [
        'label' => 'Remboursements clients',
        'columns' => [
            $col('numero', 'N° remboursement', true, false, true),
            $col('date', 'Date'),
            $col('client', 'Client'),
            $col('facture', 'Facture', false, true),
            $col('source', 'Source', false, true),
            $col('montant', 'Montant'),
            $col('mode', 'Mode de paiement', false, true),
            $actions,
        ],
    ],

    'pos-sales' => [
        'label' => 'Ventes POS',
        'columns' => [
            $select,
            $col('ticket', 'Ticket', true, false, true),
            $col('date', 'Date'),
            $col('client', 'Client'),
            $col('total', 'Total'),
            $col('paiement', 'Paiement'),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    // ── Achats ──────────────────────────────────────────────────────────

    'expenses-with-invoice' => [
        'label' => 'Dépenses avec facture',
        'columns' => [
            $select,
            $col('reference', 'N° facture', true, false, true),
            $col('designation', 'Désignation'),
            $col('date', 'Date'),
            $col('total', 'Montant'),
            $col('categorie', 'Catégorie', false, true),
            $col('document', 'Document'),
            $col('recurrence', 'Récurrence', false, true),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'expenses-without-invoice' => [
        'label' => 'Dépenses sans facture',
        'columns' => [
            $select,
            $col('designation', 'Désignation', true, false, true),
            $col('date', 'Date'),
            $col('total', 'Montant'),
            $col('categorie', 'Catégorie', false, true),
            $col('recurrence', 'Récurrence', false, true),
            $col('statut', 'Statut'),
            $col('document', 'Document importé'),
            $actions,
        ],
    ],

    'expenses' => [
        'label' => 'Dépenses',
        'columns' => [
            $select,
            $col('reference', 'Désignation', true, false, true),
            $col('date', 'Date'),
            $col('total', 'Montant'),
            $col('type', 'Catégorie', false, true),
            $actions,
        ],
    ],

    'supplier-purchase-orders' => [
        'label' => 'BC fournisseurs',
        'columns' => [
            $select,
            $col('numero', 'Numéro', true, false, true),
            $col('fournisseur', 'Fournisseur'),
            $col('date', 'Date'),
            $col('echeance', 'Échéance', false, true),
            $col('devise', 'Devise', false, true),
            $col('total', 'Total'),
            $col('document', 'Document'),
            $actions,
        ],
    ],

    'supplier-delivery-notes' => [
        'label' => 'BL fournisseurs',
        'columns' => [
            $select,
            $col('numero', 'Numéro', true, false, true),
            $col('fournisseur', 'Fournisseur'),
            $col('date', 'Date'),
            $col('reception_prevue', 'Réception prévue', false, true),
            $col('statut', 'Statut'),
            $col('conversion', 'Conversion', false, true),
            $col('total', 'Total'),
            $col('document', 'Document importé'),
            $actions,
        ],
    ],

    'receptions' => [
        'label' => 'Bons de réception',
        'columns' => [
            $select,
            $col('numero', 'Numéro', true, false, true),
            $col('fournisseur', 'Fournisseur'),
            $col('date', 'Date'),
            $col('livraison', 'Date de livraison', false, true),
            $col('statut', 'Statut'),
            $col('conversion', 'Conversion', false, true),
            $col('total', 'Total'),
            $col('document', 'Document importé'),
            $actions,
        ],
    ],

    'supplier-invoices' => [
        'label' => 'Factures fournisseurs',
        'columns' => [
            $select,
            $col('numero', 'Numéro', true, false, true),
            $col('fournisseur', 'Fournisseur'),
            $col('date', 'Date'),
            $col('echeance', 'Échéance', false, true),
            $col('devise', 'Devise', false, true),
            $col('total', 'Total'),
            $col('document', 'Facture importée'),
            $actions,
        ],
    ],

    'supplier-credit-notes' => [
        'label' => 'Avoirs fournisseurs',
        'columns' => [
            $select,
            $col('numero', 'Numéro', true, false, true),
            $col('fournisseur', 'Fournisseur'),
            $col('facture', 'Facture'),
            $col('date', 'Date'),
            $col('devise', 'Devise', false, true),
            $col('total', 'Total'),
            $col('document', 'Document'),
            $actions,
        ],
    ],

    'purchase-payments' => [
        'label' => 'Gestion paiement achats',
        'columns' => [
            $select,
            $col('facture', 'Facture', true, false, true),
            $col('fournisseur', 'Fournisseur'),
            $col('date', 'Date'),
            $col('echeance', 'Échéance', false, true),
            $col('total', 'Total'),
            $col('deja_paye', 'Payé'),
            $col('solde', 'Solde'),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    // ── Produits & stock ────────────────────────────────────────────────

    'products' => [
        'label' => 'Produits',
        'columns' => [
            $select,
            $col('type', 'Type'),
            $col('source', 'Source'),
            $col('image', 'Image'),
            $col('ref', 'Réf. / Code-barres', true, false, true, 'ref'),
            $col('nom', 'Nom / Catégorie', true, false, false, 'name'),
            $col('prix', 'Prix / Coût / TVA', true, false, false, 'sale_price'),
            $col('disponible', 'Disponible'),
            $col('reserve', 'Réservé', false, true),
            $col('alerte', 'Min. / Alerte', false, true),
            $col('depot', 'Dépôt / Emp.', false, true),
            $col('fournisseur', 'Fournisseur'),
            $col('statut', 'Statut'),
            $col('barcode', 'Code-barres', false, true),
            $col('dernier_prix', 'Dernier prix achat', false, true),
            $col('shopify_id', 'Shopify ID', false, true),
            $col('created_at', 'Date création', false, true),
            $col('updated_at', 'Date modification', false, true),
            $actions,
        ],
    ],

    'stock-magasin' => [
        'label' => 'Stock magasin',
        'columns' => [
            $select,
            $col('ref', 'Référence', true, false, true),
            $col('nom', 'Produit'),
            $col('prix_achat', 'Prix achat', false, true),
            $col('prix_vente', 'Prix vente', false, true),
            $col('stock', 'Stock'),
            $col('seuils', 'Seuils min/alerte', false, true),
            $col('emplacement', 'Emplacement', false, true),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'stock-enligne' => [
        'label' => 'Stock en ligne',
        'columns' => [
            $select,
            $col('ref', 'Référence', true, false, true),
            $col('nom', 'Produit'),
            $col('stock', 'Stock'),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'stock-inventory' => [
        'label' => 'Inventaire',
        'columns' => [
            $col('ref', 'Référence', true, false, true),
            $col('nom', 'Produit'),
            $col('theorique', 'Stock théorique'),
            $col('reel', 'Stock réel'),
            $col('ecart', 'Écart'),
            $col('depot', 'Dépôt', false, true),
            $actions,
        ],
    ],

    'stock-movements' => [
        'label' => 'Mouvements de stock',
        'columns' => [
            $col('date', 'Date'),
            $col('produit', 'Produit', true, false, true),
            $col('type', 'Type'),
            $col('quantite', 'Quantité'),
            $col('depot', 'Dépôt', false, true),
            $col('reference', 'Référence', false, true),
            $col('utilisateur', 'Utilisateur', false, true),
        ],
    ],

    'stock-alerts' => [
        'label' => 'Alertes de stock',
        'columns' => [
            $col('produit', 'Produit', true, false, true),
            $col('ref', 'Référence'),
            $col('stock', 'Stock actuel'),
            $col('minimum', 'Stock minimum'),
            $col('depot', 'Dépôt', false, true),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'warehouses' => [
        'label' => 'Dépôts',
        'columns' => [
            $col('nom', 'Nom', true, false, true),
            $col('code', 'Code', false, true),
            $col('adresse', 'Adresse', false, true),
            $col('statut', 'Statut'),
            $col('emplacements', 'Emplacements', false, true),
            $actions,
        ],
    ],

    'locations' => [
        'label' => 'Emplacements',
        'columns' => [
            $col('depot', 'Dépôt'),
            $col('code', 'Code', true, false, true),
            $col('libelle', 'Libellé'),
            $col('stock', 'Articles', false, true),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'stock-replenishment' => [
        'label' => 'Réapprovisionnement',
        'columns' => [
            $col('produit', 'Produit', true, false, true),
            $col('ref', 'Référence'),
            $col('stock', 'Stock'),
            $col('minimum', 'Minimum'),
            $col('fournisseur', 'Fournisseur', false, true),
            $col('suggestion', 'Quantité suggérée'),
            $actions,
        ],
    ],

    // ── Finance ─────────────────────────────────────────────────────────

    'financial' => [
        'label' => 'Trésorerie',
        'columns' => [
            $col('date', 'Date'),
            $col('reference', 'Référence', true, false, true),
            $col('type', 'Type'),
            $col('tiers', 'Tiers'),
            $col('mode', 'Mode', false, true),
            $col('montant', 'Montant'),
            $col('solde', 'Solde', false, true),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'financial-receivables' => [
        'label' => 'Créances',
        'columns' => [
            $col('client', 'Client', true, false, true),
            $col('facture', 'Facture'),
            $col('date', 'Date'),
            $col('echeance', 'Échéance', false, true),
            $col('total', 'Total'),
            $col('solde', 'Solde'),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'financial-debts' => [
        'label' => 'Dettes',
        'columns' => [
            $col('fournisseur', 'Fournisseur', true, false, true),
            $col('facture', 'Facture'),
            $col('date', 'Date'),
            $col('echeance', 'Échéance', false, true),
            $col('total', 'Total'),
            $col('solde', 'Solde'),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'financial-movements' => [
        'label' => 'Mouvements financiers',
        'columns' => [
            $col('date', 'Date'),
            $col('reference', 'Référence', true, false, true),
            $col('origine', 'Origine'),
            $col('type', 'Type'),
            $col('libelle', 'Libellé'),
            $col('compte', 'Compte', false, true),
            $col('entree', 'Entrée'),
            $col('sortie', 'Sortie'),
            $col('solde', 'Solde'),
            $col('utilisateur', 'Utilisateur', false, true),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'financial-declarations' => [
        'label' => 'Déclarations',
        'columns' => [
            $col('periode', 'Période', true, false, true),
            $col('type', 'Type'),
            $col('montant', 'Montant'),
            $col('statut', 'Statut'),
            $col('date_depot', 'Date dépôt', false, true),
            $actions,
        ],
    ],

    // ── CRM ─────────────────────────────────────────────────────────────

    'clients' => [
        'label' => 'Clients',
        'columns' => [
            $select,
            $col('code', 'Code', true, false, true),
            $col('client', 'Client', true, false, false, 'name'),
            $col('type', 'Type', false, true),
            $col('contact', 'Contact', true, false, false, 'phone'),
            $col('ville', 'Ville', false, true, false, 'city'),
            $col('ice', 'ICE', false, true),
            $col('statut', 'Statut'),
            $col('created_at', 'Date création', false, true),
            $actions,
        ],
    ],

    'suppliers' => [
        'label' => 'Fournisseurs',
        'columns' => [
            $select,
            $col('code', 'Code', true, false, true),
            $col('fournisseur', 'Fournisseur'),
            $col('contact', 'Contact'),
            $col('telephone', 'Téléphone', false, true),
            $col('ville', 'Ville', false, true),
            $col('ice', 'ICE', false, true),
            $col('statut', 'Statut'),
            $col('created_at', 'Date création', false, true),
            $actions,
        ],
    ],

    // ── RH ──────────────────────────────────────────────────────────────

    'hr-employees' => [
        'label' => 'Salariés',
        'columns' => [
            $col('matricule', 'Matricule', true, false, true),
            $col('nom', 'Nom'),
            $col('poste', 'Poste'),
            $col('departement', 'Département', false, true),
            $col('contrat', 'Contrat', false, true),
            $col('statut', 'Statut'),
            $col('date_entree', 'Date entrée', false, true),
            $actions,
        ],
    ],

    'hr-contracts' => [
        'label' => 'Contrats',
        'columns' => [
            $col('salarie', 'Salarié', true, false, true),
            $col('type', 'Type'),
            $col('debut', 'Début'),
            $col('fin', 'Fin', false, true),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'hr-attendance' => [
        'label' => 'Présences',
        'columns' => [
            $col('salarie', 'Salarié', true, false, true),
            $col('date', 'Date'),
            $col('entree', 'Entrée'),
            $col('sortie', 'Sortie', false, true),
            $col('duree', 'Durée'),
            $col('statut', 'Statut'),
        ],
    ],

    'hr-leaves' => [
        'label' => 'Congés',
        'columns' => [
            $col('salarie', 'Salarié', true, false, true),
            $col('type', 'Type'),
            $col('debut', 'Début'),
            $col('fin', 'Fin'),
            $col('jours', 'Jours'),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'hr-payroll' => [
        'label' => 'Rémunérations',
        'columns' => [
            $col('salarie', 'Salarié', true, false, true),
            $col('periode', 'Période'),
            $col('brut', 'Brut'),
            $col('net', 'Net'),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'hr-documents' => [
        'label' => 'Documents RH',
        'columns' => [
            $col('salarie', 'Salarié', true, false, true),
            $col('type', 'Type'),
            $col('titre', 'Titre'),
            $col('date', 'Date'),
            $col('expiration', 'Expiration', false, true),
            $actions,
        ],
    ],

    'hr-compensations' => [
        'label' => 'Avances & compensations',
        'columns' => [
            $col('salarie', 'Salarié', true, false, true),
            $col('type', 'Type'),
            $col('montant', 'Montant'),
            $col('date', 'Date'),
            $col('statut', 'Statut'),
            $actions,
        ],
    ],

    'hr-history' => [
        'label' => 'Historique RH',
        'columns' => [
            $col('date', 'Date'),
            $col('salarie', 'Salarié', true, false, true),
            $col('evenement', 'Événement'),
            $col('details', 'Détails', false, true),
            $col('utilisateur', 'Utilisateur', false, true),
        ],
    ],

];
