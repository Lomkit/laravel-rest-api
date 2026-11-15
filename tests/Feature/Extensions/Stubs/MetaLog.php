<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;

final class MetaLog
{
    /** @var list<SearchMetaContext> */
    public static array $search = [];

    /** @var list<DetailsMetaContext> */
    public static array $details = [];

    /** @var list<OperationDocumentationContext> */
    public static array $operations = [];

    /** @var list<array{bool, bool}> */
    public static array $documented = [];

    /** @var array<string, mixed>|null */
    public static ?array $searchMeta = null;

    /** @var array<string, mixed>|null */
    public static ?array $detailsMeta = null;

    public static mixed $value = null;

    public static function reset(): void
    {
        self::$search = [];
        self::$details = [];
        self::$operations = [];
        self::$documented = [];
        self::$searchMeta = null;
        self::$detailsMeta = null;
        self::$value = null;
    }
}
