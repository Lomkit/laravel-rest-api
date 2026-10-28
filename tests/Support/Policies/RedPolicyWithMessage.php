<?php

namespace Lomkit\Rest\Tests\Support\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class RedPolicyWithMessage
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the list of models.
     */
    public function viewAny($user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view($user, Model $model)
    {
        return Response::deny('You don\'t have permission to view user');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create($user)
    {
        return Response::deny('You don\'t have permission to create user');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update($user, Model $model)
    {
        return Response::deny('You don\'t have permission to update user');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($user, Model $model)
    {
        return Response::deny('You don\'t have permission to delete user');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore($user, Model $model)
    {
        return Response::deny('You don\'t have permission to restore user');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete($user, Model $model)
    {
        return Response::deny('You don\'t have permission to force delete user');
    }
}
