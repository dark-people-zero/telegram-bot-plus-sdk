<?php

namespace DarkPeople\TelegramBot\Reply;

use Illuminate\Support\Facades\Cache;

final class CacheReplyListenerStore implements ReplyListenerStore
{
    public const KEY_PREFIX = 'telegram:listen_reply:'; // telegram:listen_reply:{scope}

    public function get(string $scope): ?PendingReply
    {
        $raw = Cache::get(self::KEY_PREFIX . $scope);

        if (!is_array($raw)) return null;

        // payload produced by BaseArraySerializable::serialize()
        return PendingReply::deserialize($raw);
    }

    public function put(string $scope, PendingReply $pending, int $ttlSeconds): void
    {
        Cache::put(self::KEY_PREFIX . $scope, $pending->serialize(), max(1, $ttlSeconds));
    }

    public function forget(string $scope): void
    {
        Cache::forget(self::KEY_PREFIX . $scope);
    }
}
