<?php

namespace App\Notifications;

use App\Models\Audit;
use App\Notifications\Channels\PreferredNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AuditPerformedNotification extends Notification
{
    use Queueable;

    protected $audit;

    /**
     * Create a new notification instance.
     */
    public function __construct(Audit $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable->status !== 'active' || ! $notifiable->receive_notifications) {
            return [];
        }

        return ['database', PreferredNotificationChannel::class];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $actor = $this->audit->user;
        $actorName = $actor ? $actor->full_name : 'Sistema';
        $description = $this->audit->description;
        
        $message = $actorName . " " . $description;

        return [
            'audit_id' => $this->audit->id,
            'event' => $this->audit->event,
            'auditable_type' => $this->audit->auditable_type,
            'auditable_id' => $this->audit->auditable_id,
            'message' => $message,
            'actor_name' => $actorName,
            'title' => $this->getFriendlyTitle(),
        ];
    }

    /**
     * @return array{title:string, body:string, data:array<string, mixed>}
     */
    public function toPreferredChannel(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['title'],
            'body' => $data['message'],
            'data' => [
                'type' => 'system_notification',
                'audit_id' => $data['audit_id'],
                'event' => $data['event'],
                'auditable_type' => $data['auditable_type'],
                'auditable_id' => $data['auditable_id'],
            ],
        ];
    }

    protected function getFriendlyTitle()
    {
        $class = class_basename($this->audit->auditable_type);
        $event = $this->audit->event;

        $titles = [
            'created' => 'Nuevo registro',
            'updated' => 'Actualización',
            'deleted' => 'Eliminación',
        ];

        $modelNames = [
            'Loan' => 'de préstamo',
            'Credit' => 'de crédito',
            'Sale' => 'de venta',
            'Expense' => 'de gasto',
            'AssetLoan' => 'de préstamo de activo',
        ];

        $title = $titles[$event] ?? 'Actividad';
        $model = $modelNames[$class] ?? '';

        return trim("$title $model");
    }
}
