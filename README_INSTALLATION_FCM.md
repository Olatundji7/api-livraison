# Installation FCM — MA Livraison

Cette extension enregistre les appareils FCM et envoie les notifications depuis Laravel.
Elle n'utilise pas de worker de queue : l'envoi est déclenché directement par l'API.

## 1. Fichiers
Copier les dossiers `app/` et `database/` de cette archive dans la racine Laravel
`~/htdocs/ma.ma3hsarl.com`, en fusionnant les dossiers sans supprimer les fichiers existants.

## 2. Configuration
Dans `.env`, ajouter (ne jamais publier le contenu du fichier JSON) :

```env
FIREBASE_PROJECT_ID=ma-livraison-43517
FIREBASE_CREDENTIALS=/home/UTILISATEUR/htdocs/ma.ma3hsarl.com/storage/app/private/firebase/service-account.json
```

Remplacer `UTILISATEUR` par le vrai nom du compte LWS. Le chemin peut être vérifié depuis le dossier du projet avec :
`pwd` puis ajouter `/storage/app/private/firebase/service-account.json`.

Dans `config/services.php`, ajouter à l'intérieur du tableau retourné :

```php
'firebase' => [
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'credentials' => env('FIREBASE_CREDENTIALS'),
],
```

## 3. Modèle User
Dans `app/Models/User.php`, ajouter cette relation dans la classe :

```php
public function fcmTokens()
{
    return $this->hasMany(\\App\\Models\\FcmToken::class);
}
```

## 4. Routes API
En haut de `routes/api.php`, ajouter :
`use App\\Http\\Controllers\\Api\\FcmTokenController;`

Dans le groupe `Route::middleware('auth:sanctum')->group(function () {`, ajouter :
```php
Route::post('/fcm-tokens', [FcmTokenController::class, 'store']);
Route::delete('/fcm-tokens', [FcmTokenController::class, 'destroy']);
```

## 5. Déclencheurs à ajouter
- Dans `OrderController::store`, après la création réussie de `$order` et avant le `return response()`, envoyer une notification aux administrateurs :
```php
\\App\\Models\\User::where('role', 'admin')->each(function ($admin) use ($order) {
    app(\\App\\Services\\FcmService::class)->sendToUser(
        $admin, 'Nouvelle commande MA Livraison',
        'Une nouvelle commande n°' . $order->id . ' attend votre validation.',
        ['type' => 'new_order', 'order_id' => (string) $order->id]
    );
});
```
- Dans `FedaPayService`, juste après `$order->update([...]);` dans le bloc `$local === 'paid'`, notifier les livreurs disponibles :
```php
\\App\\Models\\Deliverer::with('user')
    ->where('status', 'disponible')
    ->get()
    ->each(function ($driver) use ($order) {
        if ($driver->user) {
            app(\\App\\Services\\FcmService::class)->sendToUser(
                $driver->user, 'Nouvelle course disponible',
                'La commande n°' . $order->id . ' vient d’être payée et est disponible.',
                ['type' => 'available_order', 'order_id' => (string) $order->id]
            );
        }
    });
```
- Dans `AdminController::assignOrder`, après le contrôle `if (! $result['ok']) ...` et avant le `return response()`, notifier le livreur attribué :
```php
$driverUser = \\App\\Models\\User::find($result['order']->driver_id);
if ($driverUser) {
    app(\\App\\Services\\FcmService::class)->sendToUser(
        $driverUser, 'Course attribuée',
        'La commande n°' . $result['order']->id . ' vous a été attribuée.',
        ['type' => 'assigned_order', 'order_id' => (string) $result['order']->id]
    );
}
```

## 6. Migration et cache
Depuis SSH LWS :
```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

## 7. Vérification
Après intégration Flutter, l'application doit appeler `POST /api/fcm-tokens` avec le token FCM et le Bearer Sanctum.
La suppression du token à la déconnexion est `DELETE /api/fcm-tokens` avec `{ "token": "..." }`.

Les notifications ne seront reçues que par les utilisateurs ayant ouvert l'application au moins une fois,
accepté les autorisations de notification et enregistré leur token. Les notifications Android en arrière-plan
sont affichées par Android avec un message FCM contenant `notification`.
