<?php

namespace Lomkit\Rest\Validation\Search;

enum LevelBounds
{
    case Paged;
    case Limited;
    case Owner;

    public function paged(): bool
    {
        return $this === self::Paged;
    }

    public function bounded(): bool
    {
        return $this !== self::Owner;
    }
}
