<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Artisan;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeCommandCommand extends Command
{
    protected $signature = 'telegram:make:command
        {name : Class name, e.g. StartCommand}
        {--name= : Telegram command name (SDK $name), e.g. start}
        {--description= : SDK description}
        {--force : Overwrite if file exists}';

    protected $description = 'Generate a Telegram Command class (App\\Telegram\\Commands).';

    public function handle(Filesystem $files): int
    {
        $rawName = (string) $this->argument('name');

        if (Str::contains($rawName, ['/', '\\'])) {
            $this->error('Chaining/subfolder is not supported. Use class name only (e.g. StartCommand).');
            return self::FAILURE;
        }

        $class = Str::studly($rawName);
        if ($class === '') {
            $this->error('Invalid class name.');
            return self::FAILURE;
        }

        $dir = app_path('Telegram/Commands');
        $path = $dir . DIRECTORY_SEPARATOR . $class . '.php';

        if ($files->exists($path) && ! $this->option('force')) {
            $this->error("File already exists: {$path}");
            $this->line('Use --force to overwrite.');
            return self::FAILURE;
        }

        $stubPath = __DIR__ . '/../Stubs/telegram-command.stub';
        if (! $files->exists($stubPath)) {
            $this->error("Stub not found: {$stubPath}");
            return self::FAILURE;
        }

        $sdkName = (string) ($this->option('name') ?? '');
        $sdkName = trim($sdkName);

        if ($sdkName === '') {
            // Fallback: StartCommand -> start, Help -> help, FooBarCommand -> foo_bar
            $base = Str::replaceLast('Command', '', $class);
            $base = $base !== '' ? $base : $class;
            $sdkName = Str::snake($base);
        }

        // Telegram command names should be lowercase and typically use underscores.
        $sdkName = Str::lower($sdkName);

        $description = (string) ($this->option('description') ?? '');
        $description = trim($description);

        $namespace = 'App\\Telegram\\Commands';

        $stub = $files->get($stubPath);
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ sdk_name }}', '{{ description }}'],
            [$namespace, $class, $sdkName, addslashes($description)],
            $stub
        );

        $files->ensureDirectoryExists($dir);
        $files->put($path, $content);

        $this->info("Created: {$namespace}\\{$class}");
        $this->line($path);

        return self::SUCCESS;
    }
}
