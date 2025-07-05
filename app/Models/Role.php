<?php

namespace App\Models;

// --- IMPORTA LOS MODELOS Y CLASES NECESARIAS ---
use App\Models\Permission;
use App\Models\User; // <-- ¡Asegúrate de importar tu modelo User!
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /**
     * Un rol puede tener varios permisos.
     * Sobrescribimos este método para asegurarnos de que utiliza
     * nuestro modelo App\Models\Permission personalizado.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class, // Usa tu clase Permission
            config('permission.table_names.role_has_permissions'),
            'role_id',
            'permission_id'
        );
    }

    /**
     * Un rol puede ser asignado a varios usuarios.
     * Sobrescribimos este método para asegurarnos de que utiliza
     * nuestro modelo App\Models\User personalizado.
     */
    public function users(): MorphToMany // <-- AÑADE ESTE MÉTODO COMPLETO
    {
        return $this->morphedByMany(
            User::class, // <-- La clave es usar tu clase User
            'model',
            config('permission.table_names.model_has_roles'),
            'role_id',
            config('permission.column_names.model_morph_key')
        );
    }
}
