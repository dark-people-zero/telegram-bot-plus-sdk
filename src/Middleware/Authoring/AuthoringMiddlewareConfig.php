<?php

declare(strict_types=1);

namespace DarkPeople\TelegramBot\Middleware\Authoring;

final class AuthoringMiddlewareConfig
{
    /**
     * @param AuthoringMiddlewareRule[] $rules
     */
    public function __construct(
        public readonly array $rules = [],
    ) {}

    /**
     * Helper (optional): ambil rule untuk bot tertentu (atau global).
     *
     * @return AuthoringMiddlewareRule[]
     */
    public function rulesForBot(string $botName): array
    {
        return array_values(array_filter(
            $this->rules,
            static function (AuthoringMiddlewareRule $rule) use ($botName): bool {
                // null = semua bot
                if ($rule->forBot === null) return true;

                return in_array($botName, $rule->forBot, true);
            }
        ));
    }
}
