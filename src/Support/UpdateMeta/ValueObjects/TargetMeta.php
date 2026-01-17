<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects;

use Telegram\Bot\Objects\User;

final class TargetMeta extends User
{
    public readonly ?string $role; // creator|administrator|member|restricted|left|kicked|unknown (chat role)
    public readonly array $all;

    public static function fromUser(array $user, ?string $role = null) : self {
        $target = new self($user[0]->toArray());
        $target->role = $role;
        $target->all = $user;
        
        return $target;
    }

}
