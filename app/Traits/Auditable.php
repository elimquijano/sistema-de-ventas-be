<?php

namespace App\Traits;

use App\Models\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            // En creación, solo guardamos campos clave, no todo el modelo
            $important = $model->getAuditImportantFields();
            $values = array_intersect_key($model->getAttributes(), array_flip($important));
            $model->audit('created', null, $values);
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            
            // Campos a ignorar siempre (ruido)
            $ignored = ['updated_at', 'created_at', 'uuid', 'remember_token', 'email_verified_at'];
            foreach ($ignored as $field) unset($changes[$field]);
            
            if (empty($changes)) return;

            $oldValues = [];
            $newValues = [];

            foreach ($changes as $key => $newValue) {
                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $newValue;
            }

            $model->audit('updated', $oldValues, $newValues);
        });
        // ... rest of bootAuditable (deleted/restored) stays similar but using lean patterns
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

        $auditData = [
            'event' => $event,
            'user_id' => $userId,
            'old_values' => $old,
            'new_values' => $new,
            'metadata' => $metadata,
            'url' => Request::fullUrl(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'business_id' => $this->business_id ?? (Auth::user() ? Auth::user()->business_id : null),
        ];

        if (method_exists($this, 'getAuditParent')) {
            $parent = $this->getAuditParent();
            if ($parent) {
                $auditData['parent_type'] = get_class($parent);
                $auditData['parent_id'] = $parent->id;
            }
        }

        $this->audits()->create($auditData);
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
