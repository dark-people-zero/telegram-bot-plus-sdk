<?php

namespace DarkPeople\TelegramBot\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Console i18n dictionary loader and formatter.
 *
 * Provides:
 * - loading language dictionaries from multiple folders with override priority
 * - placeholder interpolation using "{key}" syntax
 * - optional caching for loaded dictionaries
 *
 * Config:
 * - telegram.console.lang_path (string|null) : custom lang folder (highest priority)
 */
final class ConsoleI18n
{
    /**
     * Base cache key for language dictionaries.
     *
     * @var string
     */
    public const CACHE_KEY = 'telegram:locale';

    /**
     * Load the dictionary for a given language.
     *
     * Folder search order:
     * 1) default package lang folder
     * 2) config override lang folder (highest priority)
     *
     * When enabled, the loaded dictionary will be cached per language.
     *
     * @param string $lang Language code (e.g. "id", "en").
     * @return array<string, mixed> Dictionary array (supports nested arrays).
     */
    public static function getLang(string $lang): array
    {
        $lang = strtolower($lang);
        $dict = [];

        if(RuntimeConfig::useCache()) {
            $cacheKey = self::CACHE_KEY . ':' . $lang;
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) return $cached;
        }

        foreach (self::langPaths() as $path) {
            $fileDict = self::loadLangFile($path, $lang);
            if ($fileDict !== null) {
                $dict = array_replace($dict, $fileDict);
            }
        }

        if(RuntimeConfig::useCache()) {
            $cacheKey = self::CACHE_KEY . ':' . $lang;
            Cache::forever($cacheKey, $dict);
        }

        return $dict;
    }

    /**
     * Translate a key path from the selected language dictionary.
     *
     * Supports dot-separated key paths (e.g. "help.usage").
     * Placeholders use "{name}" syntax and are replaced from $vars.
     *
     * @param string $keys Dot-separated key path.
     * @param array<string, string> $vars Placeholder variables.
     * @param string|null $lang Override language code; defaults to RuntimeConfig::lang().
     * @return string
     */
    public static function get(string $keys, array $vars = [], string $lang = null): string
    {
        $lang ??= RuntimeConfig::lang();
        $disc = self::getLang($lang);
        $val = '';
        foreach (explode('.', $keys) as $key) {
            $t = $disc[$key] ?? null;
            if (is_string($t)) {
                $val = $t;
                break;
            }
            $disc = $disc[$key] ?? [];
        }

        foreach ($vars as $k => $v) {
            $val = str_replace('{' . $k . '}', (string) $v, $val);
        }

        return empty($val) ? $keys : $val;
    }

    /**
     * Clear cached dictionaries for all detected languages.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        $listLang = self::listLang();
        foreach ($listLang as $key) {
            Cache::forget(self::CACHE_KEY . ':' . $key);
        }
    }

    /**
     * Get dictionary folder paths in priority order.
     *
     * Only existing directories are included.
     *
     * @return list<string>
     */
    private static function langPaths(): array
    {
        $default = dirname(__DIR__, 1) . '/resources/lang/console';

        $custom = null;
        if (function_exists('config')) $custom = config('telegram.console.lang_path');

        $paths = [$default];
        if (is_string($custom) && $custom !== '') $paths[] = $custom;

        $out = [];
        foreach ($paths as $p) {
            $p = rtrim($p, DIRECTORY_SEPARATOR);
            if ($p !== '' && is_dir($p) && !in_array($p, $out, true)) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * Load a language dictionary file from a path.
     *
     * Supported formats:
     * - "{path}/{lang}.php"  (must return an array)
     * - "{path}/{lang}.json" (decoded into array)
     *
     * @param string $path Base folder.
     * @param string $lang Language code.
     * @return array<string, string>|null
     */
    private static function loadLangFile(string $path, string $lang): ?array
    {
        $php = $path . DIRECTORY_SEPARATOR . $lang . '.php';
        if (is_file($php)) {
            $data = require $php;
            return is_array($data) ? $data : null;
        }

        $json = $path . DIRECTORY_SEPARATOR . $lang . '.json';
        if (is_file($json)) {
            $raw = @file_get_contents($json);
            if (!is_string($raw) || $raw === '') return null;

            $decoded = json_decode($raw, true);

            // invalid json
            if (!is_array($decoded)) return null;

            // prefer object-like JSON (associative), ignore numeric lists
            if (!self::isAssocArray($decoded)) return null;

            /** @var array<string, mixed> $decoded */
            return $decoded;
        }

        return null;
    }
    
    /**
     * List available languages found in configured language folders.
     *
     * A language is detected from filenames:
     * - "{lang}.php"
     * - "{lang}.json"
     *
     * @return list<string>
     */
    private static function listLang() {
        $paths = self::langPaths();
        $langs = [];
        foreach ($paths as $path) {
            $files = scandir($path);
            if ($files === false) continue;

            foreach ($files as $file) {
                if (str_ends_with($file, '.php')) {
                    $langs[] = substr($file, 0, -4);
                } elseif (str_ends_with($file, '.json')) {
                    $langs[] = substr($file, 0, -5);
                }
            }
        }

        return array_values(array_unique($langs));
    }

    /**
     * Determine whether an array is associative.
     *
     * Compatible with PHP < 8.1.
     *
     * @param array<mixed> $arr
     * @return bool
     */
    private static function isAssocArray(array $arr): bool
    {
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i++) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get an interactive prompt message for "listen reply" flow.
     *
     * Resolution rules (highest priority wins):
     * 1) Language dictionary from configured lang sources (built-in + package + override path).
     * 2) Prompt templates override from $promptValue (passed from CommandNode / PlusCommand).
     * 3) Default variables from $promptVarible (passed from CommandNode / PlusCommand).
     *
     * Behavior:
     * - The method tries to find a prompt template by the given $key.
     * - If not found, it falls back to 'prompt.default'.
     * - Variables are merged and replaced using "{var}" placeholders.
     *
     * Prompt key rules:
     * - For arguments: use argument name (e.g. "name", "age").
     * - For options:   use option long name without leading dashes (e.g. "--age" => "age").
     *
     * Common variables:
     * - {type}: "argument" or "option"
     * - {text}: the prompt key (e.g. "name", "age")
     *
     * @param string $key Prompt key (argument/option name without dashes).
     * @param array<string, mixed> $vars Variables to replace into the prompt template.
     * @param array<string, string> $promptValue Prompt template overrides keyed by prompt key.
     * @param array<string, array<string, mixed>> $promptVarible Default variables keyed by prompt key.
     * @param string|null $lang Optional language override.
     * @return string Rendered prompt message.
     */
    public static function getPrompt(
        string $key,
        array $vars = [],
        array $promptValue = [],
        array $promptVarible = [],
        ?string $lang = null
    ): string {
        $lang ??= RuntimeConfig::lang();

        $dict = self::getLang($lang);

        $promptDict = is_array($dict['prompt'] ?? null) ? $dict['prompt'] : [];

        if (isset($promptValue[$key]) && is_string($promptValue[$key])) {
            $template = $promptValue[$key];
        } elseif (isset($promptDict[$key]) && is_string($promptDict[$key])) {
            $template = $promptDict[$key];
        } else {
            $template = $promptDict['default'] ?? "Masukkan nilai untuk {type} *`{text}`*:";
        }

        $defaultVars = [];

        if (isset($promptVarible['default']) && is_array($promptVarible['default'])) $defaultVars = $promptVarible['default'];

        if (isset($promptVarible[$key]) && is_array($promptVarible[$key])) {
            $defaultVars = array_replace($defaultVars, $promptVarible[$key]);
        }

        foreach ($promptVarible as $k => $v) {
            if (is_string($k) && !is_array($v)) $defaultVars[$k] = $v;
        }

        $allVars = array_replace($defaultVars, $vars);

        foreach ($allVars as $k => $v) {
            $template = str_replace('{' . $k . '}', (string) $v, $template);
        }

        return (string) $template;
    }


}
