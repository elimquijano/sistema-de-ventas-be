<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define la programación de comandos de la aplicación.
     * Aquí es donde configuras las tareas programadas (cron jobs).
     */
    protected function schedule(Schedule $schedule): void
    {
        // Ejemplo:
        // $schedule->command('sync:modules')->daily();
    }

    /**
     * Registra los comandos para la aplicación.
     */
    protected function commands(): void
    {
        // Esta línea es clave: busca y carga automáticamente
        // todos los comandos que crees en la carpeta `app/Console/Commands`.
        $this->load(__DIR__.'/Commands');

        // También carga los comandos definidos en routes/console.php
        require base_path('routes/console.php');
    }
}
