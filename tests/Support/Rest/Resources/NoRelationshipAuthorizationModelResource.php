<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Tests\Support\Models\NoRelationshipAuthorizedModel;

class NoRelationshipAuthorizationModelResource extends ModelResource
{
    public static $model = NoRelationshipAuthorizedModel::class;

    /**
     * Check if gating is enabled.
     *
     * @return bool
     */
    public function isGatingEnabled(): bool
    {
        return true;
    }
}
