<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

// Canal order.{id} — écouté par le client ET le livreur assigné à cette commande
Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    $order = Order::find($orderId);

    if (! $order) {
        return false;
    }

    return $user->id === $order->client_id || $user->id === $order->deliverer_id;
});

// Canal deliverer.{id}.orders — écouté uniquement par le livreur concerné
Broadcast::channel('deliverer.{delivererId}.orders', function ($user, $delivererId) {
    return (int) $user->id === (int) $delivererId;
});
