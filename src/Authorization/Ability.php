<?php

namespace Lomkit\Rest\Authorization;

enum Ability: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case ForceDelete = 'forceDelete';

    public function isClassLevel(): bool
    {
        return $this === self::Create;
    }

    public function needsSoftDeletes(): bool
    {
        return $this === self::Restore || $this === self::ForceDelete;
    }
}
