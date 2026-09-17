<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Bloque l'accès si l'utilisateur connecté n'a pas le rôle attendu.
     * Usage dans les routes : ->middleware('role:admin')
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== $role) {
            return response()->json([
                'message' => "Accès réservé au rôle : {$role}.",
            ], 403);
        }

        return $next($request);
    }
}
