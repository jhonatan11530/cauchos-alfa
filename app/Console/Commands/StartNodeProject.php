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
    protected $signature = 'node:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Instala las dependencias de Node si no existen y ejecuta npm start';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Carpeta dentro de la raíz de Laravel
        $path = base_path('whatsapp-server');

        $this->info("Ruta del proyecto Node: {$path}");

        // Validar carpeta
        if (!is_dir($path)) {
            $this->error("La carpeta no existe: {$path}");
            return self::FAILURE;
        }

        // Validar package.json
        if (!file_exists($path . '/package.json')) {
            $this->error("No existe package.json en: {$path}");
            return self::FAILURE;
        }

        /*
         * ============================================================
         * npm install
         * ============================================================
         */

        if (!is_dir($path . '/node_modules')) {
            $this->info('node_modules no existe.');
            $this->info('Ejecutando npm install...');
            $install = new Process(
                ['npm', 'install'],
                $path
            );

            // npm install puede tardar
            $install->setTimeout(null);
            $install->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if (!$install->isSuccessful()) {
                $this->error('npm install falló.');
                return self::FAILURE;
            }

            $this->info('npm install completado.');
        } else {
            $this->info('node_modules ya existe. No se ejecutará npm install.');
        }

        /*
         * ============================================================
         * Verificar si el servicio ya está corriendo
         * ============================================================
         */

        $check = new Process(
            ['pgrep', '-f', 'npm start']
        );
        $check->run();
        if ($check->isSuccessful()) {
            $this->warn('npm start ya está ejecutándose.');
            return self::SUCCESS;
        }

        /*
         * ============================================================
         * npm start
         * ============================================================
         */

        $this->info('Iniciando npm start...');
        $process = new Process(
            ['npm', 'start'],
            $path
        );

        // No cerrar el proceso cuando termine el comando Artisan
        $process->setTimeout(null);
        $process->start(function ($type, $buffer) {
            file_put_contents(
                storage_path('logs/node.log'),
                $buffer,
                FILE_APPEND
            );
        });

        $this->info('Servicio Node iniciado.');
        $this->info('PID: ' . $process->getPid());

        return self::SUCCESS;
    }
}
