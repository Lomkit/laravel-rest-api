<?php

namespace Lomkit\Rest\Fields;

use Lomkit\Rest\Exceptions\InvalidFieldException;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public static function parse(self|string $direction): self
    {
        if ($direction instanceof self) {
            return $direction;
        }

        return self::tryFrom(strtolower($direction)) ?? throw InvalidFieldException::unknownSortDirection($direction);
    }
}
