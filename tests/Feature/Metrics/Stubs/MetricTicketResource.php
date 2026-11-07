<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

class MetricTicketResource extends TicketResource
{
    public static bool $duplicated = false;

    public static bool $misdeclared = false;

    public static bool $late = false;

    /** @var list<string> */
    public static array $reads = [];

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Integer::make('team_id'), Text::make('title')->searchable(), Text::make('status')];
    }

    #[\Override]
    public function text(): TextSearch
    {
        return Contains::make();
    }

    #[\Override]
    public function instructions(): array
    {
        return [NewestFirst::make()];
    }

    #[\Override]
    public function actions(): array
    {
        self::$reads[] = 'actions '.spl_object_id($this);

        return parent::actions();
    }

    #[\Override]
    public function metrics(): array
    {
        self::$reads[] = 'metrics '.spl_object_id($this);

        return [
            TicketBacklog::make()->middleware('throttle:60,1'),
            TicketsByStatus::make(),
            GuestTicketBacklog::make()->withoutMiddleware('auth'),
            ReportedTicketBacklog::make(),
            UncheckedTicketBacklog::make()->withoutSearching(),
            QuietTicketBacklog::make(),
            TypedTicketBacklog::make(),
            ...(self::$duplicated ? [TicketBacklog::make()] : []),
            ...(self::$misdeclared ? [SlugTypedMetric::make()] : []),
            ...(self::$late ? [LateTicketBacklog::make()] : []),
        ];
    }

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        if ($this->request()->user()?->getAuthIdentifier() === TicketPolicy::LEAD) {
            return $query;
        }

        return $query->where('tickets.status', 'open');
    }

    #[\Override]
    public function searching(SearchContext $search): void
    {
        if ($search->filtersOn('title') && ! $search->filtersOn('status')) {
            throw ValidationException::withMessages(['search.filters' => 'The title filter requires a status filter.']);
        }
    }

    public static function reset(): void
    {
        self::$duplicated = false;
        self::$misdeclared = false;
        self::$late = false;
        self::$reads = [];
    }
}
