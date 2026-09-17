<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class LocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Order $order,
        public float $lat,
        public float $lng,
    ) {}

    /** Canal du contrat : order.{id} */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('order.'.$this->order->id)];
    }

    /** Nom d'événement du contrat : LocationUpdated */
    public function broadcastAs(): string
    {
        return 'LocationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'timestamp' => now()->toISOString(),
        ];
    }
}
