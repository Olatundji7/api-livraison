# Mise à jour MA Livraison — commande avec position uniquement

Cette version du backend correspond au nouveau parcours de commande :

- aucun point de départ demandé au client ;
- aucun choix manuel de livreur ;
- la position de livraison est obligatoire (latitude + longitude) ;
- l'adresse textuelle de destination est facultative ;
- l'instruction (`note`) est obligatoire ;
- le panier utilise le même parcours ;
- le choix du livreur est géré côté backend/livreurs.

## Installation locale

Depuis le dossier Laravel :

```bash
php artisan migrate
php artisan optimize:clear
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

Pour vérifier rapidement la validation :

```bash
php artisan route:list --path=api/orders
```

## Données attendues pour une commande

```json
{
  "type": "livraison",
  "destination_latitude": 6.37,
  "destination_longitude": 2.42,
  "note": "Ex: description de votre commande."
}
```

`destination_address` peut être envoyé si l'application en dispose, mais n'est plus obligatoire.
