<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

enum SearchShape
{
    case Page;
    case Rows;
    case Aggregate;

    private const array ROW_KEYS = [
        'text' => true,
        'trashed' => true,
        'filters' => true,
        'gates' => true,
        'scopes' => true,
        'sorts' => true,
        'selects' => true,
        'aggregates' => true,
        'includes' => true,
        'instructions' => true,
        'limit' => true,
    ];

    private const array SET_KEYS = [
        'text' => true,
        'trashed' => true,
        'filters' => true,
        'scopes' => true,
        'instructions' => true,
    ];

    /** @return array<string, true> */
    public function keys(): array
    {
        return match ($this) {
            self::Page => [...self::ROW_KEYS, 'page' => true],
            self::Rows => self::ROW_KEYS,
            self::Aggregate => self::SET_KEYS,
        };
    }

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
