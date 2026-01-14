<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Middleware\Scan;

use DarkPeople\TelegramBot\Middleware\Contracts\PlusMiddlewareMeta;
use DarkPeople\TelegramBot\Middleware\Contracts\TelegramMiddleware;
use DarkPeople\TelegramBot\Support\Scan\DiscoveredScan;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use RuntimeException;

final class MiddlewareScanner
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param string[] $paths
     * @return DiscoveredMiddleware[]
     */
    public function scan(array $paths): array
    {
        $out = [];
        $seen = []; // class-string => sourcePath

        $appRoot = rtrim(app_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        foreach ($paths as $dir) {
            if (!$this->files->isDirectory($dir)) {
                continue; // ok: path invalid/optional
            }

            foreach ($this->files->allFiles($dir) as $file) {
                // Skip non-php file defensively
                if (strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $pathname = $file->getPathname();

                // Tegaskan kontrak scanner: file harus berada di bawah app_path()
                if (!Str::startsWith($pathname, $appRoot)) {
                    throw new RuntimeException(
                        "MiddlewareScanner expects scanned files under app_path(). Got: {$pathname}"
                    );
                }

                // Map file path -> FQCN (App\...)
                $relative = Str::after($pathname, $appRoot);
                $relative = str_replace(['/', '\\'], '\\', $relative);
                $class = 'App\\' . Str::replaceLast('.php', '', $relative);

                // Fail fast: file ada tapi class tidak bisa di-autoload
                if (!class_exists($class)) {
                    throw new RuntimeException(
                        "Discovered PHP file but class is not autoloadable.\nFile: {$pathname}\nExpected FQCN: {$class}"
                    );
                }

                $ref = new ReflectionClass($class);

                // Skip abstract class (normal)
                if ($ref->isAbstract()) {
                    continue;
                }

                // Hanya candidate middleware jika implement TelegramMiddleware
                if (!$ref->implementsInterface(TelegramMiddleware::class)) {
                    continue;
                }

                // Duplicate detection (fail fast)
                if (isset($seen[$class])) {
                    $first = $seen[$class];
                    throw new RuntimeException(
                        "Duplicate middleware class discovered: {$class}\n- {$first}\n- {$pathname}"
                    );
                }
                $seen[$class] = $pathname;

                $scan = new DiscoveredScan(
                    class: $class,
                    sourceType: 'scan:middleware',
                    sourcePath: $pathname
                );

                // Metadata bersifat opsional: hanya ambil jika implement PlusMiddlewareMeta
                if (!$ref->implementsInterface(PlusMiddlewareMeta::class)) {
                    $out[] = new DiscoveredMiddleware(scan: $scan);
                    continue;
                }

                /** @var PlusMiddlewareMeta $instance */
                $instance = app()->make($class);

                // events: opsional (user bisa taruh di config atau belum isi)
                $events = $instance->events();
                $events = $this->normalizeStringList($events);

                // forBot: opsional (validate tipe supaya fail fast)
                $forBotRaw = $instance->forBot();

                if (!is_null($forBotRaw) && !is_string($forBotRaw) && !is_array($forBotRaw)) {
                    throw new RuntimeException("Invalid forBot() metadata on {$class}. Must be string|array|null. File: {$pathname}");
                }

                $forBot = null;
                if (!is_null($forBotRaw)) {
                    $list = $this->normalizeStringList($forBotRaw);

                    // empty list -> treat as global
                    $forBot = $list ?: null;
                }
                

                // commands: opsional (hanya meaningful untuk event 'command', tapi scanner tidak enforce)
                $commandsRaw = $instance->commands();
                $commands = is_null($commandsRaw) ? null : $this->normalizeStringList($commandsRaw);

                $out[] = new DiscoveredMiddleware(
                    scan: $scan,
                    events: $events ?: null,
                    forBot: $forBot,
                    commands: $commands ?: null
                );
            }
        }

        return $out;
    }

    /**
     * @param string[]|string|null $value
     * @return string[]
     */
    private function normalizeStringList(array|string|null $value): array
    {
        if (is_null($value)) {
            return [];
        }

        $items = is_array($value) ? $value : [$value];

        $items = array_map(static function ($v): string {
            return is_string($v) ? trim($v) : '';
        }, $items);

        $items = array_values(array_filter($items, static fn (string $v) => $v !== ''));

        return $items;
    }
}
