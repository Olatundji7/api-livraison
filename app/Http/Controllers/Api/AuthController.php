<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
