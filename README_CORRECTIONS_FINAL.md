# MA Livraison — corrections finales

## 1. Erreur `distance_travelled_km`
L'erreur SQL provenant de la table `orders` vient d'une migration manquante dans une base déjà créée.

Après avoir remplacé l'ancienne API par cette version :

```bash
php artisan migrate
php artisan storage:link
```

La migration `2026_10_08_000000_add_distance_and_financial_fields_to_orders.php` ajoute uniquement les colonnes absentes et ne demande pas de supprimer la base.

## 2. Prix de course
- Base : 500 FCFA + 300 FCFA/km.
- Au-delà de 3 km : réduction de 20 % sur le prix de la course.
- `delivery_fee` = prix net de la course après réduction.
- `service_fee` = 100 FCFA, séparé du prix de la course.
- L'administration affiche seulement `delivery_fee` pour le prix de la course.

## 3. Frais FedaPay
Le client paie le total de la commande, incluant les 100 FCFA de service MA Livraison.
Les frais réellement retournés par FedaPay sont enregistrés dans `fedapay_fee` et la comptabilité MA Livraison calcule :

```text
net_service_revenue = max(0, 100 - frais_fedapay)
```

Le détail des 100 FCFA et du frais FedaPay est masqué de la liste des commandes administrateur.

## 4. Distance réelle
La clôture d'une course exige au moins deux positions GPS pendant la période de la course. La distance est la somme des distances entre positions consécutives du livreur. Aucune ligne droite pickup → destination ne remplace l'historique GPS pour le prix final.

## 5. Panier
Le panier est maintenant stocké en MySQL :
- `carts`
- `cart_items`

Routes :
- `GET /api/cart`
- `POST /api/cart/items`
- `PATCH /api/cart/items/{item}`
- `DELETE /api/cart/items/{item}`
- `DELETE /api/cart`
- `POST /api/cart/checkout`

Le checkout vérifie le stock, recalcule le prix côté serveur, crée la commande, enregistre ses produits et vide le panier.

## 6. Carte client / livreur
Le client peut choisir le point exact sur OpenStreetMap ou appuyer sur `Utiliser ma position`. Le livreur dispose d'un bouton `Voir la carte et la position client` sur sa course.

## 7. Publicités
Les images publicitaires utilisent des URL normalisées côté Flutter et des aperçus avec gestion d'erreur. Pour les nouvelles installations, exécutez impérativement `php artisan storage:link` après migration.
