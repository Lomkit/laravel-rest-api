<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Lomkit\Rest\Confirmation\Confirmation;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\PendingMutation;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

class ShowResource extends Resource
{
    public static string $model = Show::class;

    public static bool $instances = false;

    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('id'),
            Text::make('title')
                ->searchable()
                ->rules('max:80')
                ->creationRules('required')
                ->examples('Opening night', 'Closing night'),
            Text::make('summary')
                ->nullable()
                ->rules('min:8'),
            Number::make('price')
                ->nullable()
                ->meta(['unit' => 'EUR'])
                ->examples(12.5),
            Integer::make('seats')->rules('min:0'),
            Boolean::make('featured'),
            Date::make('starts_on')->creationRules('required'),
            DateTime::make('starts_at')->nullable(),
            Enum::make('status')
                ->creationRules('required')
                ->offered([ShowStatus::Planned, ShowStatus::OnSale]),
            Enum::make('level'),
            Json::make('settings')->nullable(),
            Text::make('secret')
                ->writeOnly()
                ->nullable(),
            Text::make('press_note')
                ->inputOnly()
                ->nullable(),
            Text::make('headline')->virtual(),
            DateTime::make('updated_at')->readonly(),
        ];
    }

    #[\Override]
    public function versioned(): bool
    {
        return true;
    }

    #[\Override]
    public function mutateConfirmation(PendingMutation $mutation): ?Confirmation
    {
        $onSale = array_filter($mutation->entries, fn (MutationNode $entry): bool => $entry->operation === MutationOperation::Update && ($entry->attributes['status'] ?? null) === ShowStatus::OnSale->value);

        return $onSale === [] ? null : Confirmation::make('Open the sale of '.count($onSale).' shows?')->data(['shows' => count($onSale)]);
    }

    /** @param Collection<int, Show> $models */
    #[\Override]
    public function forceDestroyConfirmation(Collection $models): ?Confirmation
    {
        return $models->count() < 2 ? null : Confirmation::make("Delete {$models->count()} shows for good?")->data(['rows' => $models->count()]);
    }

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsTo::make('stage', StageResource::class),
            BelongsToMany::make('artists', ArtistResource::class)->pivotFields([Text::make('role')->nullable()]),
            HasMany::make('comments', CommentResource::class),
            MorphToMany::make('labels', LabelResource::class)->pivotFields([Integer::make('position')->nullable()]),
        ];
    }

    #[\Override]
    public function scopes(): array
    {
        return [Scope::make('startingFrom')->fields([Date::make('from'), Integer::make('min_seats')->default(0)])];
    }

    #[\Override]
    public function instructions(): array
    {
        return self::$instances ? [OnlyFeatured::make()] : [OnlyFeatured::class];
    }

    #[\Override]
    public function actions(): array
    {
        if (self::$instances) {
            return [RescheduleShows::make(), CancelShows::make(), ImportShows::make(), ArchiveShows::make()];
        }

        return [
            RescheduleShows::class,
            CancelShows::class,
            ImportShows::class,
            ArchiveShows::class,
        ];
    }

    #[\Override]
    public function metrics(): array
    {
        if (self::$instances) {
            return [ShowCount::make(), ShowsPerMonth::make(), ShowsByStatus::make(), ShowsByLevel::make(), SeatsByLevel::make(), TopStages::make(), SeatsGoal::make(), Occupancy::make()];
        }

        return [
            ShowCount::class,
            ShowsPerMonth::class,
            ShowsByStatus::class,
            ShowsByLevel::class,
            SeatsByLevel::class,
            TopStages::class,
            SeatsGoal::class,
            Occupancy::class,
        ];
    }

    #[\Override]
    public function detailsMeta(): array
    {
        return ['label' => 'Shows', 'default_sort' => ['field' => 'starts_on', 'direction' => 'desc']];
    }

    #[\Override]
    public function searchMeta(SearchContext $search, LengthAwarePaginator $page): array
    {
        return ['listed' => count($page->items())];
    }

    #[\Override]
    public function gates(): array
    {
        return ['feature'];
    }

    #[\Override]
    public function text(): TextSearch
    {
        return Contains::make();
    }
}
