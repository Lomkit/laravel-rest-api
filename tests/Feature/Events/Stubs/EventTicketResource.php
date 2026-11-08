<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\ReportOutage;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class EventTicketResource extends TicketResource
{
    #[\Override]
    public function actions(): array
    {
        return [
            CloseEventTickets::make(),
            AnnotatedCloseEventTickets::make(),
            AtomicCloseEventTickets::make(),
            TransactionlessCloseEventTickets::make()->withoutTransaction(),
            QueuedCloseEventTickets::make(),
            ReportOutage::make(),
        ];
    }

    #[\Override]
    public function describing(): void
    {
        EventLog::record('describing');
    }

    #[\Override]
    public function described(array $details): void
    {
        EventLog::record('described '.implode(',', array_keys($details)));
    }

    #[\Override]
    public function searching(SearchContext $search): void
    {
        EventLog::$payloads[] = $search;
        EventLog::record('searching');

        if ($search->filtersOn('title') && ! $search->filtersOn('status')) {
            throw ValidationException::withMessages(['search.filters' => 'The title filter requires a status filter.']);
        }
    }

    #[\Override]
    public function searched(LengthAwarePaginator $page): void
    {
        EventLog::record('searched '.implode(',', $page->getCollection()->modelKeys()).' of '.$page->total());
    }

    #[\Override]
    public function mutatingAll(array $entries): void
    {
        EventLog::record('mutatingAll '.implode(',', array_map(fn (MutationNode $entry): string => $entry->operation->value, $entries)));

        $titles = array_map(fn (MutationNode $entry): mixed => $entry->attributes['title'] ?? null, $entries);

        if (in_array('Spam', $titles, true)) {
            throw ValidationException::withMessages(['mutate' => 'Spam tickets are refused.']);
        }
    }

    #[\Override]
    public function mutatedAll(array $operations): void
    {
        EventLog::record('mutatedAll '.json_encode($operations));
    }

    #[\Override]
    public function operating(Action $action, ActionFields $fields): void
    {
        EventLog::$payloads[] = $fields;
        EventLog::record('operating '.$action->uriKey());
    }

    #[\Override]
    public function operated(Action $action, ActionFields $fields, int $impacted): void
    {
        EventLog::$payloads[] = $fields;
        EventLog::record('operated '.$action->uriKey().' '.$impacted);
    }

    #[\Override]
    public function destroyingAll(Collection $models): void
    {
        EventLog::record('destroyingAll '.implode(',', $models->modelKeys()));
    }

    #[\Override]
    public function destroyedAll(Collection $models): void
    {
        EventLog::record('destroyedAll '.implode(',', $models->modelKeys()));
    }

    #[\Override]
    public function destroying(Model $model): void
    {
        EventLog::record('destroying '.$model->getKey());
    }
}
