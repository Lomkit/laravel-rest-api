<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Tests\Support\Models\Model;

class NoExposedFieldsResource extends Resource
{
    use DisableAutomaticGates;

    public static $model = Model::class;

    public function fields(RestRequest $request): array
    {
        return [];
    }
}