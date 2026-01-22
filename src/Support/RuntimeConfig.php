<?php

namespace DarkPeople\TelegramBot\Support;

/**
 * Global runtime configuration helper.
 *
 * Provides centralized access to runtime-level decisions such as:
 * - cache enable/disable
 * - active console language
 *
 * This class acts as a thin wrapper over configuration values and
 * must remain free of side effects.
 */
final class RuntimeConfig
{
    /**
     * Determine whether runtime caching is enabled.
     *
     * When enabled, internally compiled structures such as command
     * registries and language dictionaries may be cached.
     *
     * @return bool
     */
    public static function useCache(): bool
    {
        return (bool) config('telegram.cache', false);
    }

    /**
     * Get the active console language code.
     *
     * This value is used by the console renderer and i18n system.
     *
     * @return string Language code (e.g. "id", "en").
     */
    public static function lang(): string
    {
        return (string) config('telegram.console.lang', "id");
    }
}
