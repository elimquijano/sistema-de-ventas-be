<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\NotificationDeliveryService;
use Illuminate\Notifications\Notification;

class PreferredNotificationChannel
{
    public function __construct(private readonly NotificationDeliveryService $delivery) {}

    public function send(object $notifiable, Notification $notification): ?bool
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toPreferredChannel')) {
            return null;
        }

        return $this->delivery->send(
            $notifiable,
            $notification->toPreferredChannel($notifiable)
        );
    }
}
