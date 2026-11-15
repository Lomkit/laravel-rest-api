<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresIndexAttributes;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;
use Lomkit\Rest\Text\Engine\StringListAttribute;

final class ReadersExtension extends Extension implements DeclaresIndexAttributes, RestrictsEngineQueries
{
    public static bool $excludes = false;

    /** @var list<string>|null */
    public static ?array $groups = null;

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === ReaderVoucherResource::class;
    }

    #[\Override]
    public function indexAttributes(IndexAttributesContext $context): array
    {
        EngineLog::$contexts[] = $context;

        return [StringListAttribute::make('readers')];
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        EngineLog::$contexts[] = $context;
        $reader = $context->user?->getAuthIdentifier();

        if (! is_string($reader)) {
            $context->query->matchNothing();

            return;
        }

        if (self::$excludes) {
            $context->query->whereNotIn('readers', self::$groups ?? [$reader]);

            return;
        }

        $context->query->whereIn('readers', self::$groups ?? [$reader]);
    }
}
