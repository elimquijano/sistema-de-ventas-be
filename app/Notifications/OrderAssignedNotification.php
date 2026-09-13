<?php

namespace App\Notifications;

use App\Models\Sale;
use App\Notifications\Channels\PreferredNotificationChannel;
use App\Services\OrderNotificationFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Sale $sale) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        if ($notifiable->status !== 'active' || ! $notifiable->receive_notifications) {
            return [];
        }

        return ['database', PreferredNotificationChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $formatter = app(OrderNotificationFormatter::class);

        return [
            'type' => 'order_assigned',
            'title' => 'Nuevo pedido',
            'message' => $formatter->pushBody($this->sale),
            'sale_id' => $this->sale->id,
            'sale_uuid' => $this->sale->uuid,
            'sale_number' => $this->sale->sale_number,
        ];
    }

    /** @return array{title:string, body:string, data:array<string, mixed>, whatsapp_body:string} */
    public function toPreferredChannel(object $notifiable): array
    {
        $formatter = app(OrderNotificationFormatter::class);

        return [
            'title' => 'Nuevo pedido',
            'body' => $formatter->pushBody($this->sale),
            'data' => [
                'type' => 'order_assigned',
                'sale_id' => $this->sale->id,
                'sale_uuid' => $this->sale->uuid,
                'sale_number' => $this->sale->sale_number,
            ],
            'whatsapp_body' => $formatter->whatsAppBody($this->sale),
        ];
    }
}
