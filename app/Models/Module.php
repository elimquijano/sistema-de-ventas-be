<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'route',
        'component',
        'permission',
        'sort_order',
        'parent_id',
        'type',
        'status',
        'show_in_menu',
        'auto_create_permissions',
    ];

    protected $casts = [
        'show_in_menu' => 'boolean',
        'auto_create_permissions' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Module::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Module::class, 'parent_id')->orderBy('sort_order');
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'module', 'name');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeShowInMenu($query)
    {
        return $query->where('show_in_menu', true);
    }

    public static function getTree()
    {
        return static::with('children.children.children')
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get();
    }

    public static function getMenuTree()
    {
        return static::with('children.children.children')
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->where('show_in_menu', true)
            ->orderBy('sort_order')
            ->get();
    }

    // Crear permisos automáticamente al crear el módulo
    protected static function boot()
    {
        parent::boot();

        static::created(function ($module) {
            if ($module->auto_create_permissions && $module->type === 'page') {
                $module->createDefaultPermissions();
            }
        });

        static::deleted(function ($module) {
            // Eliminar permisos relacionados
            Permission::where('module', $module->name)->delete();
        });
    }

    public function createDefaultPermissions()
    {
        $basePermissions = [
            [
                'name' => "{$this->slug}.view",
                'display_name' => "Ver {$this->name}",
                'type' => 'view',
            ],
        ];

        // Agregar más permisos según el tipo
        if ($this->type === 'page') {
            $basePermissions = array_merge($basePermissions, [
                [
                    'name' => "{$this->slug}.create",
                    'display_name' => "Crear {$this->name}",
                    'type' => 'create',
                ],
                [
                    'name' => "{$this->slug}.edit",
                    'display_name' => "Editar {$this->name}",
                    'type' => 'edit',
                ],
                [
                    'name' => "{$this->slug}.delete",
                    'display_name' => "Eliminar {$this->name}",
                    'type' => 'delete',
                ],
            ]);
        }

        foreach ($basePermissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                [
                    'display_name' => $permissionData['display_name'],
                    'module' => $this->name,
                    'type' => $permissionData['type'],
                    'description' => $permissionData['display_name'],
                    'guard_name' => 'api',
                ]
            );
        }
    }
}
