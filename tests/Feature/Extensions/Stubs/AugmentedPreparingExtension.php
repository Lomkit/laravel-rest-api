<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Query\Builder;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Extensions\Contexts\AugmentationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AugmentsQueries;
use Lomkit\Rest\Extensions\Kinds\PreparesRowChecks;

final class AugmentedPreparingExtension extends Extension implements AugmentsQueries, PreparesRowChecks
{
    public static ?string $alias = null;

    /** @var list<string> */
    public static array $gates = [];

    /** @var list<PendingChecks> */
    public static array $received = [];

    #[\Override]
    public function augment(AugmentationContext $context): void
    {
        self::$gates = $context->gates;
        self::$alias = $context->select('flag', static fn (Builder $query): Builder => $query->selectRaw("'seen'"));
    }

    #[\Override]
    public function prepareRowChecks(PendingChecks $checks): void
    {
        self::$received[] = $checks;
    }
}
