<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;

class PageCountMetaExtension extends Extension implements AddsSearchMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'pages';
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        return ['rows' => count($context->rows)];
    }
}
