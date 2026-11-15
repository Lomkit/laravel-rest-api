<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;

final class PassingCheckExtension extends Extension implements ChecksResources
{
    #[\Override]
    public static function name(): string
    {
        return 'passing';
    }

    #[\Override]
    public function checkResource(ResourceCheckContext $context): void
    {
        ExtensionLog::$checked[] = $context->resource::class;
    }
}
