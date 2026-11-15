<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresIndexAttributes;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;
use Lomkit\Rest\Text\Engine\StringListAttribute;

final class AdaptiveReadersExtension extends Extension implements DeclaresIndexAttributes, RestrictsEngineQueries, RestrictsQueries
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === ReaderVoucherResource::class;
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->where($context->query->qualifyColumn('team'), 'red');
    }

    #[\Override]
    public function indexAttributes(IndexAttributesContext $context): array
    {
        return $context->matchesStringLists ? [StringListAttribute::make('readers')] : [];
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $reader = $context->user?->getAuthIdentifier();

        if ($context->rowsRestrictedInSql) {
            return;
        }

        if (! $context->matchesStringLists || ! is_string($reader)) {
            $context->query->matchNothing();

            return;
        }

        $context->query->whereIn('readers', [$reader]);
    }
}
