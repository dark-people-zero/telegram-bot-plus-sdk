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
        $target = new self();
        $target->type = $user->isBot === true ? 'bot' : 'user';
        $target->role = $role ?: 'unknown';
        $target->title = null;
        
        $target->id = $user->id;
        $target->isBot = $user->isBot;
        $target->firstName = $user->firstName;
        $target->lastName = $user->lastName;
        $target->username = $user->username;
        $target->languageCode = $user->languageCode;
        $target->canJoinGroups = $user->canJoinGroups;
        $target->canReadAllGroupMessages = $user->canReadAllGroupMessages;
        $target->supportsInlineQueries = $user->supportsInlineQueries;

        return $target;
    }

    public static function fromSender(Chat $chat) : self {
        $target = new self();
        $target->type = 'sender_chat';
        $target->role = 'unknown';
        
        $target->id = $chat->id;
        $target->firstName = $chat->title;
        $target->lastName = $chat->lastName;
        $target->username = $chat->username;

        $target->title = $chat->title;

        return $target;
    }

    public function isBot(): bool { return $this->type === 'bot'; }
    public function isAdmin(): bool { return in_array($this->role, ['administrator', 'creator'], true); }
}
