<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;

final class MetaLog
{
    /** @var list<SearchMetaContext> */
    public static array $search = [];

    /** @var array<string, mixed>|null */
    public static ?array $searchMeta = null;

    /** @var array<string, mixed>|null */
    public static ?array $detailsMeta = null;

    public static mixed $value = null;

    public static function reset(): void
    {
        self::$search = [];
        self::$searchMeta = null;
        self::$detailsMeta = null;
        self::$value = null;
    }
}
