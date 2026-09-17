<?php

namespace App\Http\Controllers\Api\Admin;

use App\Events\OrderStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Deliverer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderAdminController extends Controller
{
    /** GET /admin/orders?statut=&date_debut=&date_fin= */
    public function index(Request $request)
    {
        $query = Order::query()->latest('cree_le');

        if ($request->filled('statut')) {
            $query->where('statut', $request->input('statut'));
        }
        if ($request->filled('date_debut')) {
            $query->whereDate('cree_le', '>=', $request->input('date_debut'));
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('cree_le', '<=', $request->input('date_fin'));
        }

        $orders = $query->paginate(20);

        return OrderResource::collection($orders);
    }

    /** POST /admin/orders/{id}/assign */
    public function assign(Request $request, Order $order)
    {
        $validator = Validator::make($request->all(), [
            'deliverer_id' => ['required', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        $deliverer = Deliverer::where('user_id', $request->input('deliverer_id'))
            ->where('statut_validation', 'valide')
            ->first();

        if (! $deliverer) {
            return response()->json(['message' => "Ce livreur n'existe pas ou n'est pas validé."], 422);
        }

        $order->update([
            'deliverer_id' => $deliverer->user_id,
            'statut' => 'traitee',
        ]);
        $order->recordStatus('traitee');

        event(new OrderStatusChanged($order));

        return response()->json([
            'order_id' => $order->id,
            'deliverer_id' => $deliverer->user_id,
            'statut' => $order->statut,
        ]);
    }
}
