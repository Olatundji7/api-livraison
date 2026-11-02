<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Modification du profil par le client connecté (informations et mot de passe).
 * Changer le téléphone ou l'e-mail (identifiant de connexion et de récupération)
 * exige le mot de passe actuel.
 */
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'client') {
            return response()->json(['message' => 'Ce profil est géré par l’administrateur.'], 403);
        }

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'current_password' => ['nullable', 'string'],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'email.email' => 'Adresse e-mail invalide.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
        ])->validate();

        $name = trim($data['name']);
        $phone = trim($data['phone']);
        $email = trim((string) ($data['email'] ?? ''));
        $email = $email === '' ? null : mb_strtolower($email);

        if (User::where('phone', $phone)->where('id', '!=', $user->id)->exists()) {
            return $this->fail('Ce numéro de téléphone est déjà utilisé par un autre compte.', 'phone');
        }

        if ($email !== null && User::whereRaw('LOWER(email) = ?', [$email])->where('id', '!=', $user->id)->exists()) {
            return $this->fail('Cette adresse e-mail est déjà utilisée par un autre compte.', 'email');
        }

        $currentEmail = $user->email ? mb_strtolower($user->email) : null;
        $sensitiveChange = $phone !== (string) $user->phone || $email !== $currentEmail;

        if ($sensitiveChange && ! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
            return $this->fail('Mot de passe actuel incorrect : il est nécessaire pour changer le téléphone ou l’e-mail.', 'current_password');
        }

        $user->update(['name' => $name, 'phone' => $phone, 'email' => $email]);

        return response()->json([
            'message' => 'Informations mises à jour.',
            'user' => $user->fresh()->load('deliverer'),
        ]);
    }

    public function password(Request $request)
    {
        $user = $request->user();

        $data = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'different:current_password'],
        ], [
            'current_password.required' => 'Saisissez votre mot de passe actuel.',
            'password.required' => 'Saisissez le nouveau mot de passe.',
            'password.min' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.',
            'password.different' => 'Le nouveau mot de passe doit être différent de l’ancien.',
        ])->validate();

        if (! Hash::check($data['current_password'], $user->password)) {
            return $this->fail('Mot de passe actuel incorrect.', 'current_password');
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        // Déconnecte les autres appareils, garde la session en cours.
        $token = $user->currentAccessToken();
        $currentId = $token instanceof PersonalAccessToken ? $token->id : null;
        $user->tokens()->when($currentId, fn ($query) => $query->where('id', '!=', $currentId))->delete();

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    private function fail(string $message, string $field)
    {
        return response()->json([
            'message' => $message,
            'errors' => [$field => [$message]],
        ], 422);
    }
}
