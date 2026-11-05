<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'in:android,web,ios'],
        ]);

        $request->user()->fcmTokens()->updateOrCreate(
            ['token' => $data['token']],
            ['platform' => $data['platform'] ?? 'android']
        );

        return response()->json(['message' => 'Appareil enregistré pour les notifications.']);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        $request->user()->fcmTokens()->where('token', $data['token'])->delete();

        return response()->json(['message' => 'Appareil désinscrit.']);
    }
}
