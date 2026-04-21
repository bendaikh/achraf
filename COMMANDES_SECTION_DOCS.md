# Section "Les Commandes" - Documentation

## Vue d'ensemble

J'ai créé une nouvelle section **"Les Commandes"** dans le menu "Gestion ventes" qui affiche toutes les commandes de toutes les sources (Shopify, Point de Vente, etc.) avec synchronisation automatique.

## Ce qui a été implémenté

### 1. **Nouveau Controller - OrderController**
- `index()`: Liste toutes les commandes avec filtres et statistiques
- `show()`: Affiche les détails d'une commande

### 2. **Routes ajoutées**
- `GET /sales/orders` → Liste des commandes
- `GET /sales/orders/{order}` → Détails d'une commande

### 3. **Nouvelle entrée dans le menu latéral**
- Ajouté "Commandes" comme premier élément dans "Gestion ventes"
- Icône de panier d'achat
- Badge bleu quand actif

### 4. **Synchronisation automatique Shopify**
Configuré dans `routes/console.php`:
```php
Schedule::command('shopify:sync-orders')->hourly();
```

### 5. **Vues créées**

#### `resources/views/sales/orders/index.blade.php`
**Fonctionnalités:**
- ✅ 4 cartes de statistiques:
  - Total des commandes
  - Commandes Shopify
  - Commandes Point de Vente
  - Chiffre d'affaires total
- ✅ Badge "Sync automatique activée" en haut
- ✅ Filtres multiples:
  - Recherche par N° commande, client, ID externe
  - Filtre par source (Shopify, POS)
  - Filtre par statut (Complété, Annulé)
  - Filtres de date (début, fin)
- ✅ Tableau des commandes avec:
  - N° de commande + ID externe
  - Badge de source (Shopify vert / POS violet)
  - Informations client
  - Date et heure
  - Total
  - Statut avec badge coloré
  - Lien "Voir détails"
- ✅ Pagination automatique
- ✅ Design moderne et responsive

#### `resources/views/sales/orders/show.blade.php`
**Fonctionnalités:**
- ✅ En-tête avec N° commande, date, source
- ✅ Badge de statut
- ✅ Informations client (nom, email, téléphone)
- ✅ Informations paiement (méthode, vendeur)
- ✅ Tableau des articles avec:
  - Désignation + référence
  - Prix unitaire
  - Quantité
  - Total par ligne
- ✅ Totaux détaillés (HT, TVA, remise, TTC)
- ✅ Notes de commande
- ✅ Bouton d'impression
- ✅ Design professionnel

## Comment fonctionne la synchronisation automatique

### Configuration du scheduler Laravel

1. **Commande définie**: `php artisan shopify:sync-orders`
2. **Planification**: Toutes les heures (hourly)
3. **Fichier**: `routes/console.php`

### Pour activer la synchronisation automatique

#### Sur Windows:
1. Ouvrez le **Planificateur de tâches** (Task Scheduler)
2. Créez une nouvelle tâche
3. Déclencheur: Toutes les minutes
4. Action: Exécuter le programme
   ```
   php
   ```
5. Arguments:
   ```
   "c:\Users\Espacegamers\Documents\achraf ecommerce\artisan" schedule:run
   ```
6. Démarrez dans:
   ```
   c:\Users\Espacegamers\Documents\achraf ecommerce
   ```

#### Sur Linux/Mac:
Ajoutez à votre crontab (`crontab -e`):
```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

### Vérification manuelle
Pour tester la synchronisation manuellement:
```bash
php artisan shopify:sync-orders
```

## Flux de données

```
┌─────────────────┐
│  Shopify Store  │
└────────┬────────┘
         │ Orders
         ▼
┌─────────────────┐     Hourly      ┌──────────────────┐
│ Laravel Cron    │ ─────────────► │ shopify:sync     │
│ (schedule:run)  │                 │ -orders command  │
└─────────────────┘                 └────────┬─────────┘
                                             │
                                             ▼
                                    ┌──────────────────┐
                                    │ Database         │
                                    │ pos_sales table  │
                                    │ source='shopify' │
                                    └────────┬─────────┘
                                             │
                                             ▼
                                    ┌──────────────────┐
                                    │ Section          │
                                    │ "Les Commandes"  │
                                    │ (orders.index)   │
                                    └──────────────────┘
```

## Différences entre les sources de commandes

| Caractéristique | Shopify | Point de Vente |
|----------------|---------|----------------|
| **Badge couleur** | Vert | Violet |
| **Champ source** | 'shopify' | null ou != 'shopify' |
| **external_id** | ID Shopify | null |
| **Synchronisation** | Automatique | Directe |
| **Client** | De Shopify | Client local |

## Statistiques affichées

1. **Total Commandes**: Compte TOUTES les commandes
2. **Shopify**: Compte seulement `source = 'shopify'`
3. **Point de Vente**: Compte `source != 'shopify'` ou `source IS NULL`
4. **Chiffre d'affaires**: Somme des `total` où `status = 'completed'`

## Filtres disponibles

### Recherche textuelle
- N° de commande (ticket_number)
- ID externe (external_id)
- Nom du client

### Filtres dropdown
- **Source**: Toutes / Shopify / Point de Vente
- **Statut**: Tous / Complété / Annulé

### Filtres de date
- Date début (date_from)
- Date fin (date_to)

## Pagination
- 20 commandes par page
- Navigation automatique
- Préserve les filtres lors de la pagination

## Accès

### URL directe
```
http://127.0.0.1:6500/sales/orders
```

### Via le menu
1. Cliquez sur "Gestion ventes" dans le menu latéral
2. Cliquez sur "Commandes" (première option)

## Prochaines étapes possibles

Si vous voulez améliorer davantage:

1. **Export Excel/CSV** des commandes
2. **Graphiques** de statistiques de ventes
3. **Notifications** quand une nouvelle commande Shopify arrive
4. **Filtres avancés** par période (aujourd'hui, cette semaine, ce mois)
5. **Recherche avancée** par montant, méthode de paiement
6. **Édition de commandes** (modifier statut, ajouter notes)
7. **Intégration e-mail** pour envoyer confirmation au client

## Troubleshooting

### Les commandes Shopify n'apparaissent pas
1. Vérifiez que l'intégration Shopify est configurée
2. Lancez manuellement: `php artisan shopify:sync-orders`
3. Vérifiez les logs: `storage/logs/laravel.log`

### La synchronisation automatique ne fonctionne pas
1. Vérifiez que le cron/task scheduler est configuré
2. Testez: `php artisan schedule:run`
3. Vérifiez: `php artisan schedule:list`

### Erreur 404 sur /sales/orders
1. Videz le cache: `php artisan route:clear`
2. Rechargez les routes: `php artisan route:list`

---

**Tout est prêt!** La section "Les Commandes" est maintenant disponible avec synchronisation automatique Shopify activée. 🎉
