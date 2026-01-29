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
     *
     * @param $user
     */
    public function viewAny($user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param       $user
     * @param Model $model
     */
    public function view($user, Model $model)
    {
        return Response::deny('You don\'t have permission to view user');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param $user
     */
    public function create($user)
    {
        return Response::deny('You don\'t have permission to create user');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param       $user
     * @param Model $model
     */
    public function update($user, Model $model)
    {
        return Response::deny('You don\'t have permission to update user');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param       $user
     * @param Model $model
     */
    public function delete($user, Model $model)
    {
        return Response::deny('You don\'t have permission to delete user');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param       $user
     * @param Model $model
     */
    public function restore($user, Model $model)
    {
        return Response::deny('You don\'t have permission to restore user');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param       $user
     * @param Model $model
     */
    public function forceDelete($user, Model $model)
    {
        return Response::deny('You don\'t have permission to force delete user');
    }
}
