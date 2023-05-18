<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;

class SoftDeletedModelResource extends Resource
{
    public static $model = SoftDeletedModel::class;

    public function exposedFields(RestRequest $request)
    {
        return [
            'id'
        ];
    }
}