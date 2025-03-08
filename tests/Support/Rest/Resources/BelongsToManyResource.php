<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;

class BelongsToManyResource extends Resource
{
    use DisableGates;

    public static $model = BelongsToManyRelation::class;

    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('model', ModelQueryChangedResource::class),
            BelongsToMany::make('models', ModelResource::class)->withPivotFields(['number']),
        ];
    }

    /**
     * Returns the list of field names for the resource.
     *
     * This method defines the fields that are part of the resource representation.
     *
     * @return array Array of field names.
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'number',
            'other_number',
        ];
    }

    /**
     * Returns an array of predefined pagination limit values.
     *
     * This method provides a fixed set of integer values that can be used to control the number
     * of items returned in paginated responses.
     *
     * @return int[] Array of allowed limit values.
     */
    public function limits(RestRequest $request): array
    {
        return [
            1,
            10,
            25,
            50,
        ];
    }
}
