<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Artisan;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeCommandCommand extends Command
{
    protected $signature = 'telegram:make:command
        {name : Class name, supports subfolder/chaining, e.g. StartCommand or Admin/StartCommand}
        {--name= : Telegram command name (SDK $name), e.g. start}
        {--description= : SDK description}
        {--force : Overwrite if file exists}';

    protected $description = 'Generate a Telegram Command class (App\\Telegram\\Commands).';

    public function handle(Filesystem $files): int
    {
        $rawName = trim((string) $this->argument('name'));
        if ($rawName === '') {
            $this->error('Invalid class name.');
            return self::FAILURE;
        }

        // Support chaining: Foo/Bar/StartCommand or Foo\Bar\StartCommand
        $normalized = str_replace('\\', '/', $rawName);
        $parts = array_values(array_filter(explode('/', $normalized), fn ($p) => $p !== ''));

        if ($parts === []) {
            $this->error('Invalid class name.');
            return self::FAILURE;
        }

        // Disallow traversal / invalid segments
        foreach ($parts as $p) {
            if ($p === '.' || $p === '..' || str_contains($p, '..')) {
                $this->error('Invalid name: path traversal is not allowed.');
                return self::FAILURE;
            }

            // allow letters, numbers, underscore only (per segment)
            if (!preg_match('/^[A-Za-z0-9_]+$/', $p)) {
                $this->error("Invalid name segment: {$p}. Use only letters, numbers, underscore, and / or \\ for folders.");
                return self::FAILURE;
            }
        }

        // Studly each segment (folders + class)
        $studlyParts = array_map(fn ($p) => Str::studly($p), $parts);
        $class = array_pop($studlyParts);

        if (!is_string($class) || $class === '') {
            $this->error('Invalid class name.');
            return self::FAILURE;
        }

        // Build dir & namespace with optional subfolders
        $subPath = $studlyParts ? implode(DIRECTORY_SEPARATOR, $studlyParts) : '';
        $dir = app_path('Telegram/Commands' . ($subPath ? DIRECTORY_SEPARATOR . $subPath : ''));
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

        $baseNamespace = 'App\\Telegram\\Commands';
        $namespace = $studlyParts ? ($baseNamespace . '\\' . implode('\\', $studlyParts)) : $baseNamespace;

        $stub = $files->get($stubPath);
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ sdk_name }}', '{{ description }}'],
            [$namespace, $class, $sdkName, addslashes($description)],
            $stub
        );

        $files->ensureDirectoryExists($dir);
        $files->put($path, $content);

        $fqcn = $namespace . '\\' . $class;
        $this->info("Created: {$fqcn}");
        $this->line($path);

        return self::SUCCESS;
    }
}
