<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ProvidesPerimeter;

class AclPerimeterExtension extends Extension implements ProvidesPerimeter
{
    #[\Override]
    public static function name(): string
    {
        return 'acl';
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->where($context->query->qualifyColumn('id'), '>', 0);
    }
}
