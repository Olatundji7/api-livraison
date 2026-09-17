<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deliverer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /** POST /auth/register */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:30', 'unique:users,telephone'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'mot_de_passe' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['client', 'livreur'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $user = User::create([
            'name' => $data['nom'],
            'telephone' => $data['telephone'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['mot_de_passe']),
            'role' => $data['role'],
        ]);

        if ($user->role === 'livreur') {
            Deliverer::create([
                'user_id' => $user->id,
                'statut_validation' => 'en_attente',
                'disponible' => false,
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'nom' => $user->name,
                'role' => $user->role,
            ],
            'token' => $token,
        ], 201);
    }

    /** POST /auth/login */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'telephone' => ['required', 'string'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('telephone', $request->input('telephone'))->first();

        if (! $user || ! Hash::check($request->input('mot_de_passe'), $user->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects.',
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'nom' => $user->name,
                'role' => $user->role,
            ],
            'token' => $token,
        ]);
    }

    /** POST /auth/logout */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }
}
