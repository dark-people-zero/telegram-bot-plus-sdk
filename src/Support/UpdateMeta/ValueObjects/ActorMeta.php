<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

final class ActorMeta
{
    public function __construct(
        public readonly ?int $userId,
        public readonly ?string $username,
        public readonly ?string $name,
        public readonly string $type, // user|bot|sender_chat|unknown
        public readonly string $role, // creator|administrator|member|restricted|left|kicked|unknown (chat role)
        public readonly array $raw = [],
    ) {}

    public static function fromTelegramUser(mixed $user, string $role = 'unknown'): self
    {
        $arr = self::normalize($user);

        $id = $arr['id'] ?? null;
        $isBot = $arr['is_bot'] ?? null;

        $first = $arr['first_name'] ?? null;
        $last  = $arr['last_name'] ?? null;
        $name  = trim(($first ?? '') . ' ' . ($last ?? ''));

        return new self(
            userId: is_numeric($id) ? (int) $id : null,
            username: isset($arr['username']) ? (string) $arr['username'] : null,
            name: $name !== '' ? $name : null,
            type: $isBot === true ? 'bot' : 'user',
            role: $role ?: 'unknown',
            raw: $arr,
        );
    }

    public static function fromSenderChat(mixed $senderChat): self
    {
        $arr = self::normalize($senderChat);

        $id = $arr['id'] ?? null;

        return new self(
            userId: is_numeric($id) ? (int) $id : null,
            username: isset($arr['username']) ? (string) $arr['username'] : null,
            name: isset($arr['title']) ? (string) $arr['title'] : null,
            type: 'sender_chat',
            role: 'unknown',
            raw: $arr,
        );
    }

    public function isBot(): bool { return $this->type === 'bot'; }
    public function isAdmin(): bool { return in_array($this->role, ['administrator', 'creator'], true); }

    private static function normalize(mixed $data): array
    {
        if ($data === null) return [];
        if (is_array($data)) return $data;

        if (is_object($data)) {
            if (method_exists($data, 'toArray')) return (array) $data->toArray();
            return json_decode(json_encode($data), true) ?: [];
        }

        return [];
    }
}
