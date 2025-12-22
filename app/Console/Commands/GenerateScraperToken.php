<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateScraperToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scraper:generate-token 
                            {--show : Display the token instead of modifying files}
                            {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a secure token for scraper authentication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $token = $this->generateToken();

        if ($this->option('show')) {
            $this->components->info('Scraper token:');
            $this->line($token);
            return Command::SUCCESS;
        }

        // Verificar si ya existe un token
        if (strlen(config('scraper.token')) > 0 && !$this->option('force')) {
            $this->components->error('Scraper token already exists.');
            $this->components->warn('Use --force to overwrite the existing token.');
            return Command::FAILURE;
        }

        // Confirmar en producción
        if ($this->laravel->environment('production') && !$this->option('force')) {
            $this->components->warn('Application is in production!');

            if (!$this->confirm('Do you really wish to generate a new scraper token?')) {
                $this->components->info('Token generation cancelled.');
                return Command::FAILURE;
            }
        }

        // Actualizar .env
        if (!$this->setTokenInEnvironmentFile($token)) {
            $this->components->error('Failed to update .env file.');
            return Command::FAILURE;
        }

        $this->components->info('Scraper token generated successfully.');
        $this->newLine();
        $this->components->twoColumnDetail('Token', $token);
        $this->newLine();
        $this->components->warn('Make sure to update your scraper configuration with this token.');

        return Command::SUCCESS;
    }

    /**
     * Generate a secure random token
     */
    protected function generateToken(): string
    {
        // Generar token de 64 caracteres (similar a APP_KEY pero más largo)
        return base64_encode(Str::random(48));
    }

    /**
     * Set the scraper token in the environment file
     */
    protected function setTokenInEnvironmentFile(string $token): bool
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->components->error('.env file not found.');
            return false;
        }

        $envContent = file_get_contents($envPath);

        // Verificar si ya existe la línea SCRAPER_TOKEN
        if (preg_match('/^SCRAPER_TOKEN=.*/m', $envContent)) {
            // Reemplazar token existente
            $envContent = preg_replace(
                '/^SCRAPER_TOKEN=.*/m',
                'SCRAPER_TOKEN=' . $token,
                $envContent
            );
        } else {
            // Agregar nueva línea después de APP_KEY
            $envContent = preg_replace(
                '/(^APP_KEY=.*$)/m',
                "$1\nSCRAPER_TOKEN=" . $token,
                $envContent
            );
        }

        file_put_contents($envPath, $envContent);

        return true;
    }
}
