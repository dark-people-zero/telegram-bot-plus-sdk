<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Commands\Scan;

use DarkPeople\TelegramBot\Commands\Contracts\PlusCommandMeta;
use DarkPeople\TelegramBot\Commands\PlusCommand;
use DarkPeople\TelegramBot\Support\Scan\DiscoveredScan;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use RuntimeException;

final class CommandScanner
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param string[] $paths
     * @return DiscoveredCommand[]
     */
    public function scan(array $paths): array
    {
        $out = [];
        $seen = []; // class-string => sourcePath

        $appRoot = rtrim(app_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        foreach ($paths as $dir) {
            if (!$this->files->isDirectory($dir)) {
                continue;
            }

            foreach ($this->files->allFiles($dir) as $file) {
                if (strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $pathname = $file->getPathname();

                if (!Str::startsWith($pathname, $appRoot)) {
                    throw new RuntimeException(
                        "CommandScanner expects scanned files under app_path(). Got: {$pathname}"
                    );
                }

                $relative = Str::after($pathname, $appRoot);
                $relative = str_replace(['/', '\\'], '\\', $relative);
                $class = 'App\\' . Str::replaceLast('.php', '', $relative);

                if (!class_exists($class)) {
                    throw new RuntimeException(
                        "Discovered PHP file but class is not autoloadable.\nFile: {$pathname}\nExpected FQCN: {$class}"
                    );
                }

                $ref = new ReflectionClass($class);

                if ($ref->isAbstract()) {
                    continue;
                }

                // must extend PlusCommand
                if (!$ref->isSubclassOf(PlusCommand::class)) {
                    continue;
                }

                // opt-in: must implement PlusCommandMeta
                if (!$ref->implementsInterface(PlusCommandMeta::class)) {
                    continue;
                }

                if (isset($seen[$class])) {
                    $first = $seen[$class];
                    throw new RuntimeException(
                        "Duplicate command class discovered: {$class}\n- {$first}\n- {$pathname}"
                    );
                }
                $seen[$class] = $pathname;

                $scan = new DiscoveredScan(
                    class: $class,
                    sourceType: 'scan:command',
                    sourcePath: $pathname
                );

                /** @var PlusCommandMeta $instance */
                $instance = app()->make($class);

                if(!$instance->autoRegister()) continue;

                $forBot = $this->normalizeForBot($instance->forBot(), $class, $pathname);
                $groups = $this->normalizeStringList($instance->groups());
                $sharedAs = $this->normalizeSharedAs($instance->sharedAs());

                $out[] = new DiscoveredCommand(
                    scan: $scan,
                    forBot: $forBot,
                    groups: $groups ?: null,
                    sharedAs: $sharedAs
                );
            }
        }

        return $out;
    }

    /**
     * @return string[]
     */
    private function normalizeStringList(array|string|null $value): array
    {
        if (is_null($value)) return [];

        $items = is_array($value) ? $value : [$value];

        $items = array_map(static fn($v) => is_string($v) ? trim($v) : '', $items);
        $items = array_values(array_filter($items, static fn(string $v) => $v !== ''));

        return $items;
    }

    /**
     * @return string[]|null
     */
    private function normalizeForBot(mixed $value, string $class, string $pathname): ?array
    {
        if (is_null($value)) return null;

        if (is_string($value)) {
            $v = trim($value);
            return $v === '' ? null : [$v];
        }

        if (is_array($value)) {
            $list = array_map(static fn($v) => is_string($v) ? trim($v) : '', $value);
            $list = array_values(array_filter($list, static fn(string $v) => $v !== ''));
            return $list ?: null;
        }

        throw new RuntimeException("Invalid forBot() on {$class}. Must be string|array|null. File: {$pathname}");
    }

    private function normalizeSharedAs(?string $value): ?string
    {
        if ($value === null) return null;
        $v = trim($value);
        return $v === '' ? null : $v;
    }
}
