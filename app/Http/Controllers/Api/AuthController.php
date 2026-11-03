<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $phone = trim((string) $request->input('phone'));
        $email = trim((string) $request->input('email'));

        if ($phone !== '' && User::where('phone', $phone)->exists()) {
            return response()->json([
                'code' => 'ACCOUNT_EXISTS',
                'message' => 'Ce compte existe déjà. Connectez-vous avec votre numéro de téléphone.',
            ], 409);
        }

        if ($email !== '' && User::where('email', $email)->exists()) {
            return response()->json([
                'code' => 'ACCOUNT_EXISTS',
                'message' => 'Ce compte existe déjà. Connectez-vous avec votre adresse e-mail.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Veuillez vérifier les informations saisies.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $user = User::create([
            'name' => trim($data['name']),
            'email' => $data['email'] ?? null,
            'phone' => trim($data['phone']),
            'password' => Hash::make($data['password']),
            'role' => 'client',
            'status' => 'actif',
        ]);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('flutter')->plainTextToken,
        ], 201);
    }

    /**
     * Vérifie le jeton Firebase auprès de l'API officielle Identity Toolkit.
     * Le rôle d'un compte existant est conservé ; un nouveau compte est client.
     */
    public function google(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'min:8', 'max:30'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Jeton Google ou numéro de téléphone invalide.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $apiKey = config('services.firebase.web_api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            return response()->json([
                'message' => 'La connexion Google n’est pas encore configurée sur le serveur.',
            ], 503);
        }

        try {
            $verification = Http::acceptJson()
                ->timeout(12)
                ->post('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($apiKey), [
                    'idToken' => $request->input('id_token'),
                ]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json([
                'message' => 'Le service de vérification Google est momentanément indisponible.',
            ], 503);
        }

        if (! $verification->successful()) {
            return response()->json([
                'message' => 'La session Google est invalide ou a expiré. Recommencez la connexion.',
            ], 401);
        }

        $firebaseUser = $verification->json('users.0');
        if (! is_array($firebaseUser)
            || empty($firebaseUser['localId'])
            || empty($firebaseUser['email'])
            || ($firebaseUser['emailVerified'] ?? false) !== true) {
            return response()->json([
                'message' => 'Votre adresse Google doit être vérifiée avant de continuer.',
            ], 403);
        }

        $firebaseUid = (string) $firebaseUser['localId'];
        $email = mb_strtolower(trim((string) $firebaseUser['email']));

        $user = User::where('firebase_uid', $firebaseUid)->first();

        if (! $user) {
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        }

        if ($user) {
            if ($user->firebase_uid !== null && $user->firebase_uid !== $firebaseUid) {
                return response()->json([
                    'code' => 'ACCOUNT_LINK_CONFLICT',
                    'message' => 'Cette adresse e-mail est déjà associée à un autre compte. Contactez le support.',
                ], 409);
            }

            // L'e-mail a été vérifié par Firebase. On associe l'identité Google
            // au compte existant sans modifier son rôle (client, driver ou admin).
            $user->firebase_uid = $firebaseUid;
            $user->email_verified_at ??= now();
            $user->save();
        } else {
            $phone = trim((string) $request->input('phone'));
            if ($phone === '') {
                return response()->json([
                    'code' => 'PHONE_REQUIRED',
                    'message' => 'Pour créer votre compte MA Livraison, veuillez renseigner votre numéro de téléphone.',
                ], 422);
            }

            if (User::where('phone', $phone)->exists()) {
                return response()->json([
                    'code' => 'ACCOUNT_EXISTS',
                    'message' => 'Ce numéro de téléphone est déjà associé à un compte. Connectez-vous avec vos identifiants habituels.',
                ], 409);
            }

            $user = User::create([
                'name' => trim((string) ($firebaseUser['displayName'] ?? 'Utilisateur MA Livraison')),
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make(Str::random(64)),
                'role' => 'client',
                'status' => 'actif',
                'firebase_uid' => $firebaseUid,
                'email_verified_at' => now(),
            ]);
        }

        if ($user->status !== 'actif') {
            return response()->json(['message' => 'Ce compte est suspendu.'], 403);
        }

        return response()->json([
            'user' => $user->load('deliverer'),
            'token' => $user->createToken('flutter-google')->plainTextToken,
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Téléphone et mot de passe sont obligatoires.', 'errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', trim($request->input('phone')))->first();
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }
        if ($user->status !== 'actif') {
            return response()->json(['message' => 'Ce compte est suspendu.'], 403);
        }

        return response()->json([
            'user' => $user->load('deliverer'),
            'token' => $user->createToken('flutter')->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token) $token->delete();
        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()->loadMissing('deliverer')]);
    }
}
