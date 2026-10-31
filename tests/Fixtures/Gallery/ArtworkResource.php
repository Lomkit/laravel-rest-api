<?php

namespace Lomkit\Rest\Tests\Fixtures\Gallery;

use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

class ArtworkResource extends Resource
{
    public static string $model = Artwork::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('title')
                ->searchable()
                ->rules('max:120')
                ->creationRules('required')
                ->updateRules('filled')
                ->meta(['label' => 'Title']),
            Enum::make('medium'),
            Json::make('dimensions')->nullable(),
            Number::make('price')
                ->nullable()
                ->updatable(false),
            Text::make('notes')
                ->nullable()
                ->writeOnly(),
        ];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('artist', ArtistResource::class),
            BelongsToMany::make('exhibitions', ExhibitionResource::class)->pivotFields([
                Integer::make('position')->nullable(),
                Text::make('wall')->updatable(false),
            ]),
            MorphTo::make('owner', CollectorResource::class),
        ];
    }

    #[\Override]
    public function scopes(RestRequest $request): array
    {
        return [Scope::make('pricedUnder')->fields([Number::make('max')])];
    }

    #[\Override]
    public function instructions(RestRequest $request): array
    {
        return [ByMedium::make()];
    }

    #[\Override]
    public function actions(RestRequest $request): array
    {
        return [RetireArtworks::make()];
    }

    #[\Override]
    public function createRules(RestRequest $request): array
    {
        return ['price' => 'required|min:0'];
    }

    #[\Override]
    public function text(RestRequest $request): TextSearch
    {
        return Contains::make();
    }
}
