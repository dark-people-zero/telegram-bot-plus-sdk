<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

use DarkPeople\TelegramBot\Support\BaseArraySerializable;

/**
 * Value object representing a command argument specification.
 *
 * This class describes a single positional argument, including its name,
 * requirement, optional validation pattern, resolved value, and description.
 *
 * Argument validation is performed using the provided regex pattern
 * (without delimiter). The resolved value is assigned only after
 * successful resolution.
 *
 * This object is cache-safe and serializable via BaseArraySerializable.
 */
final class ArgumentSpec extends BaseArraySerializable
{
    /**
     * @param string      $name        The argument name.
     * @param bool        $required    Whether the argument is required.
     * @param string|null $pattern     Optional regex pattern used for validation.
     *                                 The regex must not include delimiters.
     * @param string|null $val         The resolved argument value.
     * @param string|null $description Human-readable argument description.
     */
    public function __construct(
        public string $name,
        public bool $required = false,
        public ?string $pattern = null,
        public ?string $val = null,
        public ?string $description = null,
    ) {}
}
