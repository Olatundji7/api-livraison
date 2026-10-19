<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * Sert les images (photos livreurs, produits, publicités) via l'API.
 *
 * Avantages par rapport à /storage/... :
 * - fonctionne sans « php artisan storage:link » ;
 * - passe par le middleware CORS (indispensable pour Flutter Web : le serveur
 *   de php artisan serve envoie les fichiers statiques SANS en-têtes CORS,
 *   le navigateur bloque alors l'image).
 */
class MediaController extends Controller
{
    public function show(string $dir, string $file)
    {
        $path = $dir . '/' . $file;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
