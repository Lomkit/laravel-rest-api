<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use Lomkit\Rest\Relations\Relation;

final class RelationCheckExtension extends Extension implements ChecksResources
{
    #[\Override]
    public static function name(): string
    {
        return 'relations';
    }

    #[\Override]
    public function checkResource(ResourceCheckContext $context): void
    {
        ExtensionLog::$resources[] = [$this, $context->resource];
        $names = array_map(fn (Relation $relation): string => $relation->relation, $context->resource->relations());

        if ($names !== []) {
            $context->problem('it declares the relations ['.implode(', ', $names).'].');
        }
    }
}
