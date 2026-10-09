# API MA Livraison — Administration et publicités

Après extraction :

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

Configurer MySQL dans `.env`.

Nouvelles routes publicités :
- GET `/api/advertisements`
- GET `/api/admin/advertisements`
- POST `/api/admin/advertisements`
- PUT `/api/admin/advertisements/{id}`
- DELETE `/api/admin/advertisements/{id}`

La migration `2026_10_07_120000_create_advertisements_table.php` crée la table `advertisements`.

La page Admin Flutter consomme aussi :
- `/api/admin/dashboard`
- `/api/admin/orders`
- `/api/admin/drivers`
- `/api/admin/drivers/{id}/locations`
- `/api/admin/orders/{id}/assign`
- `/api/admin/products`
- `/api/admin/drivers` (création et modification)
