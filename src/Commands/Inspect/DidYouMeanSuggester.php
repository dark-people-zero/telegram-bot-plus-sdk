<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

/**
 * Provide "did you mean" suggestions for an unknown token.
 *
 * Strategy:
 * - Prefer prefix match (e.g. "makes" -> "make", or "make" -> "makes")
 * - Fallback to Levenshtein distance <= 1 (to avoid noisy suggestions)
 *
 * Suggestions are normalized to lowercase and limited to a maximum of 5 items.
 */
final class DidYouMeanSuggester
{
    /**
     * Suggest candidate strings for the given token.
     *
     * @param string $token Input token to be suggested (case-insensitive).
     * @param array<int,string> $candidates List of available candidates.
     * @return array<int,string> Suggested candidates (lowercased), max 5 items.
     */
    public static function suggest(string $token, array $candidates): array
    {
        $token = strtolower($token);
        if ($token === '' || empty($candidates)) return [];

        $prefix = [];
        foreach ($candidates as $c) {
            $c = strtolower($c);
            if ($c === '') continue;

            if (str_starts_with($token, $c) || str_starts_with($c, $token)) {
                $prefix[] = $c;
            }
        }
        sort($prefix);
        if (!empty($prefix)) {
            return array_slice(array_values(array_unique($prefix)), 0, 5);
        }

        $scored = [];
        foreach ($candidates as $c) {
            $c = strtolower($c);
            $d = levenshtein($token, $c);
            if ($d <= 1) $scored[$c] = $d;
        }

        if (empty($scored)) return [];

        asort($scored);
        return array_slice(array_keys($scored), 0, 5);
    }
}
