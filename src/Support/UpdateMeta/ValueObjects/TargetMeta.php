<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

use Telegram\Bot\Objects\User;

final class TargetMeta extends User
{
    public readonly ?string $role; // creator|administrator|member|restricted|left|kicked|unknown (chat role)
    public readonly array $all;

    public static function fromUser(array $user, ?string $role = null) : self {
        $target = new self();
        $target->role = $role;
        $target->all = $user;
        
        $first = $user[0];
        $target->id = $first->id;
        $target->isBot = $first->isBot;
        $target->firstName = $first->firstName;
        $target->lastName = $first->lastName;
        $target->username = $first->username;
        $target->languageCode = $first->languageCode;
        $target->canJoinGroups = $first->canJoinGroups;
        $target->canReadAllGroupMessages = $first->canReadAllGroupMessages;
        $target->supportsInlineQueries = $first->supportsInlineQueries;

        return $target;
    }

}
