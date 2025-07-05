<?php

namespace App\Console\Commands;

use App\Models\Module;
use Illuminate\Console\Command;

class SyncModules extends Command
{
    protected $signature = 'modules:sync';
    protected $description = 'Sincronizar módulos con permisos';

    public function handle()
    {
        $modules = Module::where('auto_create_permissions', true)
                        ->where('type', 'page')
                        ->get();

        foreach ($modules as $module) {
            $this->info("Sincronizando módulo: {$module->name}");
            $module->createDefaultPermissions();
        }

        $this->info('Sincronización completada');
    }
}
