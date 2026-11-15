<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;

final class ChangingSearchMeta extends Extension implements AddsSearchMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'changing';
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        foreach ($context->rows as $row) {
            $row->setAttribute('title', 'Changed');
        }

        return [];
    }
}
