<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliverer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DelivererAdminController extends Controller
{
    /** GET /admin/deliverers/pending */
    public function pending()
    {
        $deliverers = Deliverer::where('statut_validation', 'en_attente')
            ->with('user:id,name,telephone')
            ->get()
            ->map(fn (Deliverer $d) => [
                'id' => $d->id,
                'nom' => $d->user->name,
                'telephone' => $d->user->telephone,
                'piece_identite_url' => $d->piece_identite_url,
            ]);

        return response()->json(['deliverers' => $deliverers]);
    }

    /** PATCH /admin/deliverers/{id}/validate */
    public function validateDeliverer(Request $request, Deliverer $deliverer)
    {
        $validator = Validator::make($request->all(), [
            'valide' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        $deliverer->update([
            'statut_validation' => $request->boolean('valide') ? 'valide' : 'refuse',
        ]);

        return response()->json([
            'deliverer_id' => $deliverer->id,
            'statut_validation' => $deliverer->statut_validation,
        ]);
    }
}
