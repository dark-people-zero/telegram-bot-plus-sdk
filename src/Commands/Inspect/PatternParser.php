<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

/**
 * Parse and resolve command argument and option patterns.
 *
 * This utility handles:
 * - parsing SDK-style pattern strings into ArgumentSpec / OptionSpec
 * - resolving raw option tokens into consumable key-value pairs
 *
 * Pattern rules (shared by arguments and options):
 * - "{name}"           : optional
 * - "{name?}"          : required
 * - "{name: regex}"    : optional with regex
 * - "{name?: regex}"   : required with regex
 *
 * Regex is provided without delimiters.
 */
final class PatternParser
{
    /**
     * Parse an argument pattern into {@see ArgumentSpec} instances.
     *
     * Example pattern:
     * "{name} {age?: \d+}"
     *
     * @param string $pattern Raw argument pattern.
     * @return array<int, ArgumentSpec>
     */
    public static function parseArguments(string $pattern): array
    {
        $parser = self::parser($pattern);
        return array_map(
            fn ($item) => new ArgumentSpec(
                name: $item['name'],
                required: $item['required'],
                pattern: $item['pattern']
            ),
            $parser
        );
    }

    /**
     * Parse an option pattern into {@see OptionSpec} instances.
     *
     * Example pattern:
     * "{name} {force?}"
     *
     * Long and short flags are generated automatically:
     * - long  : "--name"
     * - short : "-name"
     *
     * @param string $pattern Raw option pattern.
     * @return array<int, OptionSpec>
     */
    public static function parseOptions(string $pattern): array
    {
        $parser = self::parser($pattern);

        $names = array_map(fn ($e) => (string) $e['name'], $parser);
        $shortMap = self::generateShortOptions($names);

        return array_map(
            function ($item) use ($shortMap) {
                $name = (string) $item['name'];

                // fallback super-safe: first char of normalized name
                $fallback = self::normalize($name);
                $fallback = $fallback !== '' ? $fallback[0] : 'x';

                $short = $shortMap[$name] ?? $fallback;

                return new OptionSpec(
                    long: "--$name",
                    short: "-$short",
                    required: (bool) $item['required'],
                    pattern: $item['pattern'],
                    mustHave: (bool) $item['mustHave'],
                );
            },
            $parser
        );
    }

    /**
     * Internal pattern parser shared by arguments and options.
     *
     * Extracts name, required flag, and optional regex pattern
     * from tokens wrapped in "{}".
     *
     * @param string $input Raw pattern string.
     * @return array<int, array{name: string, required: bool, pattern: string|null}>
     */
    private static function parser(string $input): array
    {
        $result = [];

        preg_match_all('/\{([^}]+)\}/', $input, $matches);

        foreach ($matches[1] as $token) {
            $token = trim($token);

            $name = $token;
            $required = false;
            $mustHave = false;
            $pattern = null;

            // split name dan pattern (jika ada)
            if (str_contains($token, ':')) {
                [$namePart, $patternPart] = array_map('trim', explode(':', $token, 2));
                $name = $namePart;
                $pattern = $patternPart !== '' ? $patternPart : null;
            }

            // cek required (?)
            if (str_ends_with($name, '?')) {
                $required = true;
                $name = substr($name, 0, -1);
            }

            // cek mustHave (!)
            if (str_ends_with($name, '!')) {
                $required = true;
                $name = substr($name, 0, -1);
            }

            $result[] = [
                'name'     => $name,
                'required' => $required,
                'pattern'  => $pattern,
                'mustHave'  => $mustHave,
            ];
        }

        return $result;
    }

    private static function generateShortOptions(array $names): array
    {
        $result = [];
        $used = [];

        foreach ($names as $origName) {
            $origName = (string) $origName;
            $n = self::normalize($origName);
            if ($n === '') $n = 'x';

            $first = $n[0]; // always exists
            $picked = null;

            // Rule 1: first letter
            if (!isset($used[$first])) {
                $picked = $first;
            }

            // Rule 2: pick a unique letter from the name (including digits)
            if ($picked === null) {
                $chars = array_values(array_unique(str_split($n)));
                foreach ($chars as $ch) {
                    if (!isset($used[$ch])) {
                        $picked = $ch;
                        break;
                    }
                }
            }

            // Rule 3: double/triple first letter: vv, vvv (max 3)
            if ($picked === null) {
                $vv = $first . $first;
                if (!isset($used[$vv])) {
                    $picked = $vv;
                } else {
                    $vvv = $first . $first . $first;
                    if (!isset($used[$vvv])) {
                        $picked = $vvv;
                    }
                }
            }

            // Rule 4: numbered: v4, v5, ...
            if ($picked === null) {
                $k = 4;
                while (isset($used[$first . $k])) {
                    $k++;
                }
                $picked = $first . $k;
            }

            $used[$picked] = true;
            $result[$origName] = $picked;
        }

        return $result;
    }

    private static function normalize(string $name): string
    {
        $name = strtolower(trim($name));
        // keep only a-z0-9 so short option stays clean
        $name = preg_replace('/[^a-z0-9]+/', '', $name) ?? '';
        return $name;
    }

    /**
     * Resolve raw option tokens into an associative array for consumption.
     *
     * Rules:
     * - "--name=dian" => ["name" => "dian"]
     * - "--model"     => ["model" => true]
     * - If an OptionSpec defines a pattern, it is used as a FILTER / extractor:
     *     "--age=u23" with pattern "\d+" => ["age" => "23"]
     *
     * Unknown options are ignored.
     *
     * @param array<int, string>     $tokens Raw option tokens.
     * @param array<int, OptionSpec> $specs  Option specifications (global + command).
     * @return array<string, mixed> Resolved option values.
     */
    public static function resolveOptions(array $tokens, array $specs): array
    {
        $resolved = [];

        // index spec by long/short flag
        $byLong = [];
        $byShort = [];
        foreach ($specs as $s) {
            $byLong[strtolower($s->long)] = $s;
            if ($s->short) $byShort[strtolower($s->short)] = $s;
        }

        foreach ($tokens as $raw) {
            $raw = trim($raw);
            if ($raw === '' || $raw[0] !== '-') continue;

            $namePart = $raw;
            $valPart = null;

            // --opt=value
            if (str_contains($raw, '=')) {
                [$namePart, $valPart] = explode('=', $raw, 2);
                $namePart = trim($namePart);
                $valPart = trim($valPart);
            }

            $flag = strtolower($namePart);

            $spec = $byLong[$flag] ?? $byShort[$flag] ?? null;
            if (!$spec) {
                // unknown option -> ignore
                continue;
            }

            // normalized key: long without leading dashes
            $key = ltrim($spec->long, '-');

            // default (no value) => true
            if ($valPart === null || $valPart === '') {
                $resolved[$key] = true;
                continue;
            }

            $val = $valPart;

            // pattern acts as FILTER/extractor
            if (is_string($spec->pattern) && trim($spec->pattern) !== '') {
                $regex = self::wrapFilterPattern($spec->pattern);

                // ambil match pertama
                if ($regex !== '' && @preg_match($regex, $valPart, $m) === 1) {
                    $val = $m[0]; // first match
                } else {
                    // kalau gak match, biarkan value asli (karena validasi sudah dilakukan di resolver)
                    $val = $valPart;
                }
            }

            $resolved[$key] = $val;
        }

        return $resolved;
    }

    /**
     * Wrap a regex pattern for option value filtering.
     *
     * The resulting regex:
     * - has no anchors
     * - is intended for partial matching / extraction
     *
     * @param string $raw Raw regex without delimiter.
     * @return string Wrapped regex.
     */
    private static function wrapFilterPattern(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';

        // escape delimiter '/'
        $body = str_replace('/', '\/', $raw);

        // no anchor, because we want partial matches / extraction
        return '/' . $body . '/u';
    }

    /**
     * Wrap a regex pattern for argument validation.
     *
     * When anchored, "^" and "$" will be applied automatically
     * if not already present.
     *
     * @param string $raw Raw regex without delimiter.
     * @param bool $anchored Whether to anchor the pattern.
     * @return string Wrapped regex.
     */
    public static function wrapPattern(string $raw, bool $anchored = true): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';

        $body = str_replace('/', '\/', $raw);

        if ($anchored) {
            if (!str_starts_with($body, '^')) $body = '^' . $body;
            if (!str_ends_with($body, '$')) $body = $body . '$';
        }

        return '/' . $body . '/u';
    }
}
