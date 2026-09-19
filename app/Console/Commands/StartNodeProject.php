<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class StartNodeProject extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'node:start
                            {--install : Forzar la instalación de dependencias incluso si node_modules ya existe}
                            {--no-dashboard : Omitir la instalación de dependencias del dashboard}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Instala todas las dependencias de Node para OpenWA y ejecuta el servicio';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Resolver ruta del proyecto OpenWA
        $path = $this->resolveOpenWaPath();

        if (!$path) {
            $this->error('No se encontró el directorio de OpenWA (se buscó en OpenWA y whatsapp-server).');
            return self::FAILURE;
        }

        $this->info("Ruta del proyecto OpenWA: {$path}");

        // Validar package.json principal
        if (!file_exists($path . '/package.json')) {
            $this->error("No existe package.json en: {$path}");
            return self::FAILURE;
        }

        $forceInstall = $this->option('install');

        /*
         * ============================================================
         * 2. Instalar dependencias principales de OpenWA
         * ============================================================
         */
        $this->info('--- Verificando dependencias principales de OpenWA ---');
        $mainNodeModules = $path . '/node_modules';

        if (!is_dir($mainNodeModules) || $forceInstall) {
            if ($forceInstall) {
                $this->info('Opción --install detectada. Instalando todas las dependencias de OpenWA...');
            } else {
                $this->info('node_modules no existe en OpenWA. Instalando dependencias...');
            }

            if (!$this->runNpmInstall($path, 'OpenWA Principal')) {
                return self::FAILURE;
            }
        } else {
            $this->info('Dependencias principales de OpenWA ya están instaladas. (Usa --install para forzar reinstalación).');
        }

        /*
         * ============================================================
         * 3. Instalar dependencias del Dashboard de OpenWA
         * ============================================================
         */
        $dashboardPath = $path . '/dashboard';
        if (!$this->option('no-dashboard') && is_dir($dashboardPath) && file_exists($dashboardPath . '/package.json')) {
            $this->info('--- Verificando dependencias del Dashboard de OpenWA ---');
            $dashboardNodeModules = $dashboardPath . '/node_modules';

            if (!is_dir($dashboardNodeModules) || $forceInstall) {
                $this->info('Instalando dependencias del Dashboard de OpenWA...');
                if (!$this->runNpmInstall($dashboardPath, 'OpenWA Dashboard')) {
                    $this->warn('No se pudieron instalar algunas dependencias del dashboard, continuando con el servidor principal.');
                }
            } else {
                $this->info('Dependencias del Dashboard ya están instaladas.');
            }
        }

        /*
         * ============================================================
         * 4. Verificar si el servicio ya está corriendo
         * ============================================================
         */
        if ($this->isServiceAlreadyRunning()) {
            $this->warn('El servicio de WhatsApp (OpenWA) ya se encuentra en ejecución.');
            return self::SUCCESS;
        }

        /*
         * ============================================================
         * 5. Iniciar servicio OpenWA (npm start)
         * ============================================================
         */
        $this->info('Iniciando OpenWA (npm start)...');

        $process = Process::fromShellCommandline('npm start', $path);
        $process->setTimeout(null);

        // Crear carpeta de logs si no existe
        $logDir = storage_path('logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $process->start(function ($type, $buffer) {
            file_put_contents(
                storage_path('logs/node.log'),
                $buffer,
                FILE_APPEND
            );
        });

        $this->info('Servicio Node (OpenWA) iniciado en segundo plano.');
        $this->info('PID: ' . $process->getPid());
        $this->info('Registro de actividad en: ' . storage_path('logs/node.log'));

        return self::SUCCESS;
    }

    /**
     * Resuelve la ruta del proyecto OpenWA buscando en nombres comunes.
     */
    private function resolveOpenWaPath(): ?string
    {
        $candidates = [
            base_path('OpenWA'),
            base_path('openwa'),
            base_path('whatsapp-server'),
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate) && file_exists($candidate . '/package.json')) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Ejecuta npm install en el directorio especificado de forma compatible con cualquier SO (Windows/Linux).
     */
    private function runNpmInstall(string $workingDir, string $label): bool
    {
        $this->info("Ejecutando 'npm install' en {$label}...");

        $install = Process::fromShellCommandline('npm install', $workingDir);
        $install->setTimeout(null);

        $install->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (!$install->isSuccessful()) {
            $this->error("Error al ejecutar npm install en {$label}.");
            return false;
        }

        $this->info("npm install en {$label} completado exitosamente.");
        return true;
    }

    /**
     * Verifica si el servicio ya está en ejecución de forma compatible con Windows y Linux.
     */
    private function isServiceAlreadyRunning(): bool
    {
        // 1. Intentar verificar si el puerto (2785 por defecto) está escuchando
        $apiUrl = config('whatsapp.api_url', 'http://localhost:2785');
        $parsedUrl = parse_url($apiUrl);
        $host = $parsedUrl['host'] ?? '127.0.0.1';
        $port = $parsedUrl['port'] ?? 2785;

        $connection = @fsockopen($host, $port, $errno, $errstr, 1.0);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }

        // 2. En Linux/Unix, probar también con pgrep
        if (PHP_OS_FAMILY !== 'Windows') {
            $check = Process::fromShellCommandline('pgrep -f "openwa|nest start"');
            $check->run();
            if ($check->isSuccessful() && !empty(trim($check->getOutput()))) {
                return true;
            }
        }

        return false;
    }
}
