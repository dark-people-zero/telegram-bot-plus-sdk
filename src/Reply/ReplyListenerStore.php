<?php

namespace DarkPeople\TelegramBot\Reply;

interface ReplyListenerStore
{
    public function get(string $scope): ?PendingReply;

    public function put(string $scope, PendingReply $pending, int $ttlSeconds): void;

    public function forget(string $scope): void;
}
