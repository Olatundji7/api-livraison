# Paiement MA Livraison — FedaPay

Le paiement final est déclenché **après que le livreur a déclaré la commande livrée**. Flutter ne reçoit jamais la clé secrète FedaPay.

## Règle financière
- Le client paie le montant final de la commande + 100 FCFA de frais MA Livraison.
- Au-delà de 3 km, une réduction de 20 % est appliquée sur le prix de la course avant les 100 FCFA de service.
- Les frais FedaPay sont une charge de MA Livraison prélevée sur l’enveloppe de 100 FCFA.
- `net_service_revenue = max(0, 100 - frais_fedapay)`.
- Le prix net de la course (`delivery_fee`) reste séparé des frais de service et des frais FedaPay.

## Configuration .env
```
FEDAPAY_ENV=sandbox
FEDAPAY_SECRET_KEY=VOTRE_CLE_SANDBOX
FEDAPAY_API_BASE_URL=https://sandbox-api.fedapay.com/v1
FEDAPAY_CALLBACK_URL=
FEDAPAY_FEE_RATE=0.018
FEDAPAY_FIXED_FEE=0
```

La valeur `FEDAPAY_FEE_RATE` sert uniquement de **repli comptable** si la transaction FedaPay ne retourne pas elle-même ses frais. Les frais réellement fournis par FedaPay sont prioritaires. FedaPay permet au marchand de choisir que les frais soient supportés par le marchand.

## Installation
```bash
composer install
php artisan migrate
php artisan storage:link
php artisan optimize:clear
php artisan serve --host=0.0.0.0 --port=8000
```


## Règle financière MA Livraison
- Le montant visible à l'administrateur pour la course est `delivery_fee` : prix net de la course après éventuelle réduction de 20 % au-delà de 3 km.
- Le `service_fee` de 100 FCFA est séparé du prix de la course.
- Les frais FedaPay sont imputés comptablement sur cette enveloppe de 100 FCFA.
- Le revenu net MA Livraison est `max(0, service_fee - fedapay_fee)`.
- Le client paie le total final, mais l'administrateur n'a pas besoin de voir le détail 100 FCFA / frais FedaPay dans la liste des commandes.

## Distance réelle
La clôture d'une course exige au moins deux points GPS enregistrés pendant la période comprise entre l'acceptation et la livraison. La distance finale est la somme des segments GPS parcourus. Aucune distance en ligne droite n'est utilisée pour remplacer un historique GPS insuffisant.

## Panier
Le panier est maintenant persistant côté MySQL (`carts`, `cart_items`). Le checkout recalcule les prix et le stock côté serveur, crée une commande et vide le panier après succès.
