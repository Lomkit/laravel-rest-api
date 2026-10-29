<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Tests\Support\Models\NoRelationshipAuthorizedModel;

class NoRelationshipAuthorizationModelResource extends ModelResource
{
    public static string $model = NoRelationshipAuthorizedModel::class;

    /**
     * Check if gating is enabled.
     */
    public function isGatingEnabled(): bool
    {
        return true;
    }
}
