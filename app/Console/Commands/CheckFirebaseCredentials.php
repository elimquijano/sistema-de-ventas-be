<?php

namespace App\Console\Commands;

use App\Services\FirebaseCloudMessagingService;
use Illuminate\Console\Command;
use Throwable;

class CheckFirebaseCredentials extends Command
{
    protected $signature = 'firebase:check';

    protected $description = 'Verificar que PHP pueda localizar y leer las credenciales de Firebase';

    public function handle(FirebaseCloudMessagingService $firebase): int
    {
        $this->line('Base del proyecto: '.base_path());
        $this->line('Storage: '.storage_path());
        $this->line('Ruta configurada: '.((string) config('services.firebase.credentials') ?: '(automática)'));

        try {
            $path = $firebase->resolvedCredentialsPath();
            $credentials = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $this->info('Credenciales Firebase encontradas y legibles.');
            $this->line('Archivo: '.$path);
            $this->line('Project ID: '.($credentials['project_id'] ?? '(no definido)'));
            $this->line('Service account: '.($credentials['client_email'] ?? '(no definido)'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->newLine();
            $this->warn('El archivo debe existir dentro del mismo servidor/contenedor que ejecuta PHP y ser legible por ese proceso.');

            return self::FAILURE;
        }
    }
}
