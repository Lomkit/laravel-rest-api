<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Support\Models\Model;

class GatedModelResource extends Resource
{
    public static string $model = Model::class;

    public function isGatingEnabled(): bool
    {
        return true;
    }

    public function relations(RestRequest $request): array
    {
        return [
            BelongsToMany::make('belongsToManyRelation', GatedBelongsToManyResource::class),
            HasMany::make('hasManyRelation', GatedHasManyResource::class),
            MorphToMany::make('morphToManyRelation', GatedMorphToManyResource::class),
            MorphedByMany::make('morphedByManyRelation', GatedMorphedByManyResource::class),
        ];
    }

    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('name'),
            Integer::make('number'),
        ];
    }
}
