<?php

namespace App\Console\Commands;

use App\Services\FirebaseCloudMessagingService;
use App\Services\GoogleAccessTokenService;
use Illuminate\Console\Command;
use Throwable;

class CheckFirebaseCredentials extends Command
{
    protected $signature = 'firebase:check';

    protected $description = 'Verificar las credenciales y la autenticación OAuth de Firebase';

    public function handle(
        FirebaseCloudMessagingService $firebase,
        GoogleAccessTokenService $accessTokens
    ): int {
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

            $accessTokens->forServiceAccount($credentials);
            $this->info('Autenticación OAuth con Google completada correctamente.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->newLine();
            $this->warn('El archivo debe existir dentro del mismo servidor/contenedor que ejecuta PHP y ser legible por ese proceso.');

            return self::FAILURE;
        }
    }
}
