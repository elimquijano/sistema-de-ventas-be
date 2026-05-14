<?php

namespace App\Notifications;

use App\Models\Audit;
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
        return ['database'];
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
        
        // Si el notificado es el mismo que hizo la acción
        if ($actor && $notifiable->id === $actor->id) {
            $message = "Has realizado una acción: " . $description;
        } else {
            $message = $actorName . " realizó una acción: " . $description;
        }

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
