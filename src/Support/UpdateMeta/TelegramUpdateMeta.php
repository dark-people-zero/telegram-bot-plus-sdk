<?php

namespace DarkPeople\TelegramBot\Support\UpdateMeta;

use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\ActorMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\RoomMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\ChangeMeta;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\PermissionBag;
use DarkPeople\TelegramBot\Support\UpdateMeta\ValueObjects\TargetMeta;

final class TelegramUpdateMeta
{
    public function __construct(
        protected string $action,
        protected ActorMeta $actor,
        protected TargetMeta $target,
        protected RoomMeta $room,
        protected ?ChangeMeta $change,
        protected PermissionBag $permissions,
        protected array $raw = [],
    ) {}

    public function action(): string { return $this->action; }
    public function actor(): ActorMeta { return $this->actor; }
    public function target(): TargetMeta { return $this->target; }
    public function room(): RoomMeta { return $this->room; }
    public function change(): ?ChangeMeta { return $this->change; }
    public function permissions(): PermissionBag { return $this->permissions; }
    public function raw(): array { return $this->raw; }
}
