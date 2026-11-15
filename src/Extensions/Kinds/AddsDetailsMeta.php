<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;

interface AddsDetailsMeta extends ExtensionKind
{
    /** @return array<string, mixed> */
    public function detailsMeta(DetailsMetaContext $context): array;
}
