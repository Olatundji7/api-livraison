# MA Livraison — version finale API Laravel + MySQL

Cette version supprime les comptes et scénarios de démonstration. Les commandes passent par MySQL et Sanctum.

## 1. Installation API

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configurer `.env` avec MySQL :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ma_livraison_db
DB_USERNAME=root
DB_PASSWORD=
```

Puis :

```bash
php artisan migrate
php artisan storage:link
php artisan app:create-admin
php artisan serve --host=0.0.0.0 --port=8000
```

## 2. Routes principales

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/drivers/{driver}/reserve`
- `POST /api/orders`
- `GET /api/orders/active`
- `GET /api/orders/{order}/tracking`
- `GET /api/driver/orders/available`
- `POST /api/driver/orders/{order}/accept`
- `POST /api/driver/orders/{order}/reject`
- `POST /api/driver/orders/{order}/start`
- `POST /api/driver/orders/{order}/pickup`
- `POST /api/driver/orders/{order}/deliver`
- `POST /api/driver/location`
- `PATCH /api/driver/status`
- `GET /api/admin/dashboard`
- `GET/POST/PUT/DELETE /api/admin/products...`

## 3. Règle importante sur les commandes

Le `driver_id` envoyé par Flutter lors de la création d'une commande est l'ID de la table `deliverers`. Laravel le transforme ensuite en `users.id` pour la colonne `orders.driver_id`.

Le livreur ne devient `occupe` qu'après l'appel `POST /api/driver/orders/{id}/accept`.

## 4. Flutter

Pour Chrome/Linux :

```bash
flutter run -d chrome --dart-define=API_BASE_URL=http://127.0.0.1:8000/api
```

Pour Android Emulator :

```bash
flutter run -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

Pour un téléphone réel, remplacer l'URL par l'adresse IP locale du PC, par exemple :

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api
```

## 5. Flux fonctionnel

Client → connexion → choix livreur → réservation → formulaire → création MySQL → livreur reçoit la commande → acceptation → démarrage → retrait → livraison → livreur redevient disponible → client voit le statut et la position GPS.

Aucun compte, commande ou paiement de test n'est créé automatiquement.

## Nouveau parcours de commande (07/10/2026)

- Le client ne choisit plus de livreur.
- Une commande client est créée avec `status=en_attente` et `driver_id=null`.
- Pour une **livraison**, le client renseigne l'adresse de départ et l'adresse de réception.
- Pour une **course_personnelle**, le client renseigne seulement l'adresse de récupération ; l'adresse de réception reste à compléter pendant le traitement.
- Les livreurs disponibles voient seulement l'existence des nouvelles commandes avant acceptation, sans les données personnelles du client.
- L'administrateur voit les commandes et peut attribuer une commande à un livreur disponible.
- Le livreur accepte ensuite la commande ; à ce moment seulement son statut devient `occupe`.
- Après livraison ou annulation, le livreur redevient `disponible`.
- Le backend impose ces règles afin que Flutter ne soit jamais la source de vérité.


## Workflow métier MA Livraison — attribution par l’entreprise

Le client ne sélectionne plus de livreur. Il crée une demande :
- `livraison` : adresse de départ + adresse de réception ;
- `course_personnelle` : adresse de récupération uniquement.

La commande arrive en `en_attente`. L'administration la voit et l'attribue à un livreur libre. Le livreur voit d'abord uniquement qu'une commande existe ; il ne peut accepter que les commandes qui lui ont été attribuées (`livreur_reserve`). Après acceptation, il devient `occupe`.
