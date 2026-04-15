<?php

namespace App\Traits;

use App\Models\Audit;
use App\Models\User;
use App\Notifications\AuditPerformedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            \Illuminate\Support\Facades\Log::info("Auditable: Evento created disparado para " . get_class($model));
            // En creación, solo guardamos campos clave, no todo el modelo
            $important = $model->getAuditImportantFields();
            $values = array_intersect_key($model->getAttributes(), array_flip($important));
            $model->audit('created', null, $values);
        });

        static::updated(function ($model) {
            \Illuminate\Support\Facades\Log::info("Auditable: Evento updated disparado para " . get_class($model));
            
            $changes = $model->getChanges();
            
            // Campos a ignorar siempre (ruido)
            $ignored = ['updated_at', 'created_at', 'uuid', 'remember_token', 'email_verified_at'];
            foreach ($ignored as $field) unset($changes[$field]);
            
            if (empty($changes)) {
                \Illuminate\Support\Facades\Log::info("Auditable: No se detectaron cambios reales tras el filtrado.");
                return;
            }

            $oldValues = [];
            $newValues = [];

            foreach ($changes as $key => $newValue) {
                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $newValue;
            }

            $model->audit('updated', $oldValues, $newValues);
        });
    }

    /**
     * Campos que vale la pena auditar en cada modelo.
     * Si el modelo no lo define, auditamos todo menos lo ignorado.
     */
    protected function getAuditImportantFields()
    {
        return property_exists($this, 'auditInclude') ? $this->auditInclude : array_keys($this->getAttributes());
    }

    protected function audit($event, $old, $new)
    {
        $userId = Auth::id();
        if (!$userId && !app()->runningInConsole()) {
            return;
        }

        // El modelo decide qué metadatos guardar (ej: nombres en vez de IDs)
        $metadata = method_exists($this, 'auditMetadata') ? $this->auditMetadata($new) : null;

        // Asegurar business_id: Prioridad al modelo, luego al usuario autenticado
        $businessId = $this->business_id ?? (Auth::user() ? Auth::user()->business_id : null);

        $auditData = [
            'event' => $event,
            'user_id' => $userId,
            'old_values' => $old,
            'new_values' => $new,
            'metadata' => $metadata,
            'url' => Request::fullUrl(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'business_id' => $businessId,
        ];

        if (method_exists($this, 'getAuditParent')) {
            $parent = $this->getAuditParent();
            if ($parent) {
                $auditData['parent_type'] = get_class($parent);
                $auditData['parent_id'] = $parent->id;
            }
        }

        // Crear la auditoría
        $audit = $this->audits()->create($auditData);

        // Notificar a los miembros del negocio
        $this->notifyBusinessMembers($audit);
    }

    protected function notifyBusinessMembers($audit)
    {
        try {
            $currentClass = get_class($this);
            
            // Lista blanca de modelos que disparan notificaciones
            $notifiableModels = [
                'App\Models\Loan',
                'App\Models\Credit',
            ];

            // Si es un proxy de Eloquent o tiene otro nombre, normalizamos
            if (!in_array($currentClass, $notifiableModels)) {
                return;
            }

            $businessId = $audit->business_id;
            if (!$businessId) {
                \Illuminate\Support\Facades\Log::warning("Auditoría {$audit->id} sin business_id. No se puede notificar.");
                return;
            }

            // Obtener usuarios activos del negocio que deseen recibir notificaciones
            $users = User::where('business_id', $businessId)
                ->where('status', 'active')
                ->where('receive_notifications', true)
                ->get();

            if ($users->count() > 0) {
                Notification::send($users, new AuditPerformedNotification($audit));
            } else {
                \Illuminate\Support\Facades\Log::info("No hay usuarios activos en el negocio {$businessId} para notificar.");
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error en notifyBusinessMembers: " . $e->getMessage());
        }
    }

    /**
     * Método centralizado para obtener la línea de tiempo profunda.
     * Cualquier modelo que use el trait puede llamar a ->getDeepTimeline()
     */
    public function getDeepTimeline()
    {
        return \App\Models\Audit::where(function($q) {
                $q->where('auditable_type', get_class($this))
                  ->where('auditable_id', $this->id);
            })
            ->orWhere(function($q) {
                $q->where('parent_type', get_class($this))
                  ->where('parent_id', $this->id);
            })
            ->with('user')
            ->latest()
            ->get();
    }

    public function audits()
    {
        return $this->morphMany(Audit::class, 'auditable')->latest();
    }
}
