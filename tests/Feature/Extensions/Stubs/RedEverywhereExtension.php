<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresIndexAttributes;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;

final class RedEverywhereExtension extends Extension implements DeclaresIndexAttributes, RestrictsEngineQueries, RestrictsQueries
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return is_a($resource::$model, Voucher::class, true);
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->where($context->query->qualifyColumn('team'), 'red');
    }

    #[\Override]
    public function indexAttributes(IndexAttributesContext $context): array
    {
        return [Text::make('team')];
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $context->query->whereIn('team', ['red']);
    }
}
