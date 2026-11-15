<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;

interface AddsSearchMeta extends ExtensionKind
{
    /** @return array<string, mixed> */
    public function searchMeta(SearchMetaContext $context): array;
}
