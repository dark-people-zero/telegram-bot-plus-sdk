<?php

namespace DarkPeople\TelegramBot\Commands\Inspect;

use DarkPeople\TelegramBot\Support\BaseArraySerializable;

/**
 * Value object representing a command option specification.
 *
 * This class defines a single command option, including its long and short
 * flags, requirement, optional pattern, resolved value, and scope.
 *
 * Option patterns are used during option consume/extraction, not as the
 * primary validation mechanism.
 *
 * This object is cache-safe and serializable via BaseArraySerializable.
 */
final class OptionSpec extends BaseArraySerializable
{
    /**
     * @param string      $long        Long option flag (e.g. "--help").
     * @param string|null $short       Short option flag (e.g. "-h").
     * @param string|null $description Human-readable option description.
     * @param bool        $required    Whether the option is required.
     * @param string|null $val         The resolved option value.
     * @param string|null $pattern     Optional regex pattern used for value
     *                                 extraction or filtering (no delimiter).
     * @param bool        $isGlobal     Whether the option is a global option.
     */
    public function __construct(
        public string $long,
        public ?string $short = null,
        public ?string $description = null,
        public bool $required = false,
        public bool $mustHave = false,
        public ?string $val = null,
        public ?string $pattern = null,
        public bool $isGlobal = false,
    ) {}
}
