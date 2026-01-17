<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

use Telegram\Bot\Objects\Chat;
use Telegram\Bot\Objects\User;

final class ActorMeta extends User
{
    public readonly string $type; // user|bot|sender_chat|unknown
    public readonly string $role; // creator|administrator|member|restricted|left|kicked|unknown (chat role)
    public readonly ?string $title;

    public static function fromUser(User $user, string $role = "unknown"): self
    {
        $target = new self($user->toArray());
        $target->type = $user->isBot === true ? 'bot' : 'user';
        $target->role = $role ?: 'unknown';
        $target->title = null;

        return $target;
    }

    public static function fromSender(Chat $chat) : self {
        $target = new self([
            "id" => $chat->id,
            "firstName" => $chat->title,
            "lastName" => $chat->lastName,
            "username" => $chat->username,
            "title" => $chat->title,
        ]);
        $target->type = 'sender_chat';
        $target->role = 'unknown';
        return $target;
    }

    public function isBot(): bool { return $this->type === 'bot'; }
    public function isAdmin(): bool { return in_array($this->role, ['administrator', 'creator'], true); }
}
