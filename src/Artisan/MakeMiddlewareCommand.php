<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Artisan;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeMiddlewareCommand extends Command
{
    protected $signature = 'telegram:make:middleware
        {name : Class name, e.g. AuthMiddleware}
        {--force : Overwrite if file exists}';

    protected $description = 'Generate a Telegram Middleware class (App\\Telegram\\Middleware).';

    public function handle(Filesystem $files): int
    {
        $rawName = (string) $this->argument('name');

        if (Str::contains($rawName, ['/', '\\'])) {
            $this->error('Chaining/subfolder is not supported. Use class name only (e.g. AuthMiddleware).');
            return self::FAILURE;
        }

        $class = Str::studly($rawName);
        if ($class === '') {
            $this->error('Invalid class name.');
            return self::FAILURE;
        }

        $dir = app_path('Telegram/Middleware');
        $path = $dir . DIRECTORY_SEPARATOR . $class . '.php';

        if ($files->exists($path) && ! $this->option('force')) {
            $this->error("File already exists: {$path}");
            $this->line('Use --force to overwrite.');
            return self::FAILURE;
        }

        $stubPath = __DIR__ . '/../Stubs/telegram-middleware.stub';
        if (! $files->exists($stubPath)) {
            $this->error("Stub not found: {$stubPath}");
            return self::FAILURE;
        }

        $namespace = 'App\\Telegram\\Middleware';

        $stub = $files->get($stubPath);
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $class],
            $stub
        );

        $files->ensureDirectoryExists($dir);
        $files->put($path, $content);

        $this->info("Created: {$namespace}\\{$class}");
        $this->line($path);

        return self::SUCCESS;
    }
}
