<?php

namespace Lomkit\Rest\Tests\Analysis;

use Illuminate\Database\Query\Builder;
use Lomkit\Rest\Extensions\Contexts\AugmentationContext;
use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AugmentsQueries;
use Lomkit\Rest\Extensions\Kinds\DeclaresInstructions;
use Lomkit\Rest\Extensions\Kinds\NarrowsLimits;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

final class TicketQueryExtension extends Extension implements AugmentsQueries, DeclaresInstructions, NarrowsLimits, RestrictsQueries
{
    /** @var list<string> */
    public array $aliases = [];

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TicketDeskResource::class;
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        if ($context->user === null) {
            $context->query->whereRaw('0 = 1');

            return;
        }

        $context->query->where($context->query->qualifyColumn('team_id'), $context->user->getAuthIdentifier());
    }

    #[\Override]
    public function augment(AugmentationContext $context): void
    {
        if (! in_array('update', $context->gates, true)) {
            return;
        }

        $context->column('team_id');
        $context->with('tags');
        $this->aliases[] = $context->select('locked', static function (Builder $query): void {
            $query->selectRaw('0');
        });
    }

    #[\Override]
    public function narrowLimits(LimitsContext $context): void
    {
        $context->allowSizes([10, 25]);
        $context->includeLimit(5);
    }

    #[\Override]
    public function instructions(InstructionsContext $context): array
    {
        return $context->resource === TicketDeskResource::class ? [RankTicketsInstruction::class, TaggedTicketsInstruction::make()] : [];
    }
}
