<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

final class PermissionBag
{
    /**
     * @param array<string> $catalogKeys
     * @param array<string, array<string, bool|null>> $bySource
     * @param array<string, bool|null> $effective
     */
    public function __construct(
        protected array $catalogKeys,
        protected array $bySource,
        protected array $effective,
    ) {}

    /** @return array<string> */
    public function keys(): array { return $this->catalogKeys; }

    /** @return array<string, bool|null> */
    public function effective(): array { return $this->effective; }

    public function can(string $key): bool
    {
        return (bool) ($this->effective[$key] ?? false);
    }

    /** @return array<string, bool|null> */
    public function fromSource(string $source): array
    {
        return $this->bySource[$source] ?? [];
    }

    /** @return array<string> */
    public function keysFromSource(string $source): array
    {
        return array_keys($this->bySource[$source] ?? []);
    }

    /** @return array<string, array<string, bool|null>> */
    public function allBySource(): array { return $this->bySource; }
}
