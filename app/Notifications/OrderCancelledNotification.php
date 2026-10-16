<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $orderId,
        public string $clientName,
        public string $orderType,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

   public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_cancelled',
            'title' => 'Commande annulée',
            'message' => "Le client {$this->clientName} a annulé la commande #{$this->orderId}.",
            'order_id' => $this->orderId,
            'client_name' => $this->clientName,
            'order_type' => $this->orderType,
            'cancelled_at' => now()->toISOString(),
        ];
    }
}
