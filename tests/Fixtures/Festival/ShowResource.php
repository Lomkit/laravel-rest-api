<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

class ShowResource extends Resource
{
    public static string $model = Show::class;

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
            Text::make('summary')->nullable(),
            Number::make('price')
                ->nullable()
                ->meta(['unit' => 'EUR'])
                ->examples(12.5),
            Integer::make('seats')->rules('min:0'),
            Boolean::make('featured'),
            Date::make('starts_on')->creationRules('required'),
            DateTime::make('starts_at')->nullable(),
            Enum::make('status')->creationRules('required'),
            Enum::make('level'),
            Json::make('settings')->nullable(),
            Text::make('secret')
                ->writeOnly()
                ->nullable(),
            Text::make('headline')->virtual(),
        ];
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
        return [OnlyFeatured::make()];
    }

    #[\Override]
    public function actions(): array
    {
        return [
            RescheduleShows::make()->maxResources(null),
            CancelShows::make()
                ->destructive()
                ->maxResources(2),
            ImportShows::make(),
            ArchiveShows::make(),
        ];
    }

    #[\Override]
    public function metrics(): array
    {
        return [
            ShowCount::make(),
            ShowsPerMonth::make(),
            ShowsByStatus::make()->withoutMiddleware('auth'),
            TopStages::make(),
            SeatsGoal::make(),
            Occupancy::make(),
        ];
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
