<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresIndexAttributes;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;

final class TeamIndexExtension extends Extension implements DeclaresIndexAttributes, RestrictsEngineQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'team';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === VoucherResource::class;
    }

    #[\Override]
    public function indexAttributes(IndexAttributesContext $context): array
    {
        return [Text::make('team')];
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        EngineLog::$calls[] = class_basename($context->resource);
        EngineLog::$users[] = $context->user;

        $context->query->whereNotIn('team', ['blue']);
    }
}
