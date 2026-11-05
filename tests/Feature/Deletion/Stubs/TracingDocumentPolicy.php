<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class TracingDocumentPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function view(?Authenticatable $user, Model $model): bool
    {
        return true;
    }

    public function delete(?Authenticatable $user, Model $model): bool|Response
    {
        return $this->decide('delete', $model);
    }

    public function restore(?Authenticatable $user, Model $model): bool|Response
    {
        return $this->decide('restore', $model);
    }

    public function forceDelete(?Authenticatable $user, Model $model): bool|Response
    {
        return $this->decide('forceDelete', $model);
    }

    private function decide(string $ability, Model $model): bool|Response
    {
        DeletionTrace::record('policy '.$ability, $model);
        $denial = DeletionTrace::$denials[$model->getKey()] ?? null;

        return is_string($denial) ? Response::deny($denial) : $denial ?? true;
    }
}
