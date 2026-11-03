<?php

namespace Lomkit\Rest\Validation\Search;

enum LevelBounds
{
    case Paged;
    case Limited;

    public function paged(): bool
    {
        return $this === self::Paged;
    }
}
