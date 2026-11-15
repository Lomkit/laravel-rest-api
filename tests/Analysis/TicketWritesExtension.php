<?php

namespace Lomkit\Rest\Tests\Analysis;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\ReadObservationContext;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;
use Lomkit\Rest\Extensions\Kinds\ObservesReads;
use Lomkit\Rest\Extensions\Kinds\ObservesWrites;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\WrittenRow;

final class TicketWritesExtension extends Extension implements GuardsWrites, ObservesReads, ObservesWrites
{
    /** @var list<string> */
    private array $history = [];

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TicketDeskResource::class;
    }

    #[\Override]
    public function guardWrite(WriteGuardContext $context): void
    {
        $creates = array_filter($context->entries ?? [], fn (MutationNode $entry): bool => $entry->operation === MutationOperation::Create);
        $frozen = array_filter($context->rows ?? [], fn (Model $row): bool => $row->getAttribute('status') === TicketStatus::Closed->value);

        if ($context->user === null || ($creates !== [] && $context->endpoint === Endpoint::Mutate)) {
            $context->refuse('Tickets are frozen.', 423);
        }

        if ($frozen !== [] || $context->action?->uriKey() === 'close-tickets') {
            $context->refuse('Closed tickets are frozen.');
        }

        if ($context->endpoint === Endpoint::ForceDestroy) {
            $context->refuse('The archive is under maintenance.', 503, ['Retry-After' => '60']);
        }
    }

    #[\Override]
    public function observeWrite(WriteObservationContext $context): void
    {
        $operations = array_map(fn (array $operation): string => json_encode($operation, JSON_THROW_ON_ERROR), $context->operations ?? []);
        $written = array_map(fn (WrittenRow $row): string => $row->resource::class.' '.json_encode($row->entry->attributes, JSON_THROW_ON_ERROR), $context->written ?? []);

        $this->history[] = $context->endpoint->value.' '.($context->impacted ?? count($context->rows ?? [])).' '.implode(',', [...$operations, ...$written]).' '.json_encode($context->fields?->user()?->getAuthIdentifier(), JSON_THROW_ON_ERROR);
    }

    #[\Override]
    public function observeRead(ReadObservationContext $context): void
    {
        $this->history[] = $context->endpoint->value.' '.count($context->rows).' of '.($context->total ?? 0).' '.json_encode([$context->result, $context->details, $context->metric?->uriKey()], JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    public function history(): array
    {
        return $this->history;
    }
}
