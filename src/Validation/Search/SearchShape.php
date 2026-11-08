<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

enum SearchShape
{
    case Page;
    case Rows;
    case Aggregate;

    /** @return list<string> */
    public function refused(): array
    {
        return match ($this) {
            self::Page => [],
            self::Rows => ['page'],
            self::Aggregate => ['gates', 'sorts', 'selects', 'aggregates', 'includes', 'limit', 'page'],
        };
    }

    public function body(Cursor $search, ValidationContext $context): SearchBodyParser
    {
        return match ($this) {
            self::Page => SearchBodyParser::keys($search, $context, LevelBounds::Paged),
            self::Rows => SearchBodyParser::keys($search, $context, LevelBounds::Limited),
            self::Aggregate => SearchBodyParser::aggregate($search, $context),
        };
    }
}
