<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;

final class ValueSearchMeta extends Extension implements AddsSearchMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'value';
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        return ['value' => MetaLog::$value];
    }
}
