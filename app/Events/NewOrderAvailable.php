<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class NewOrderAvailable implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Order $order,
        public int $delivererId,
    ) {}

    /** Canal du contrat : deliverer.{id}.orders */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('deliverer.'.$this->delivererId.'.orders')];
    }

    public function broadcastAs(): string
    {
        return 'NewOrderAvailable';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'pickup_adresse' => $this->order->pickup_adresse,
            'prix_estime' => $this->order->prix_estime,
        ];
    }
}
