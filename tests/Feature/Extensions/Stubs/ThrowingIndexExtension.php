<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use InvalidArgumentException;
use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresIndexAttributes;

final class ThrowingIndexExtension extends Extension implements DeclaresIndexAttributes
{
    #[\Override]
    public static function name(): string
    {
        return 'throwing';
    }

    #[\Override]
    public function indexAttributes(IndexAttributesContext $context): array
    {
        throw new InvalidArgumentException('The tenant directory is not configured.');
    }
}
