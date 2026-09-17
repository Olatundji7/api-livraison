<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /** POST /admin/admins */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:30', 'unique:users,telephone'],
            'mot_de_passe' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $admin = User::create([
            'name' => $data['nom'],
            'telephone' => $data['telephone'],
            'password' => Hash::make($data['mot_de_passe']),
            'role' => 'admin',
        ]);

        return response()->json([
            'admin' => ['id' => $admin->id, 'nom' => $admin->name],
        ], 201);
    }
}
