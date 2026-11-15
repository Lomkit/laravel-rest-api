<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Archive\OpenArchivePolicy;

final class DenyingDocumentPolicy extends OpenArchivePolicy
{
    #[\Override]
    public function update(?Authenticatable $user, Model $model): bool
    {
        return $model->getKey() !== 2;
    }

    #[\Override]
    public function delete(?Authenticatable $user, Model $model): bool
    {
        return $model->getKey() !== 2;
    }
}
