<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'display_name',
        'module',
        'type',
        'description',
        'guard_name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scope para búsquedas
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%")
            ->orWhere('display_name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
    }

    // Scope por módulo
    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    // Scope por tipo
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
