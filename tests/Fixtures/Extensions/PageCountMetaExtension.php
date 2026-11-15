<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;

final class PageCountMetaExtension extends Extension implements AddsSearchMeta
{
    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        return ['rows' => count($context->rows)];
    }
}
