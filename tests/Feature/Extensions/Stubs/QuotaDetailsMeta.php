<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;

final class QuotaDetailsMeta extends Extension implements AddsDetailsMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'quota';
    }

    #[\Override]
    public function detailsMeta(DetailsMetaContext $context): array
    {
        MetaLog::$details[] = $context;

        return ['plan' => 'pro', 'user' => $context->user?->getAuthIdentifier()];
    }
}
