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

        // Sécurité : un octet parasite (retour à la ligne avant « <?php » dans
        // un fichier du projet) corromprait l'image. On vide les tampons de
        // sortie avant d'envoyer le fichier.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
