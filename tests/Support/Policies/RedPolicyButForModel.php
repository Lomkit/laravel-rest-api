<?php

namespace Lomkit\Rest\Tests\Support\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class RedPolicyButForModel
{
    use HandlesAuthorization;

    static Model $model;

    public static function forModel(Model $model) {
        static::$model = $model;
    }

    /**
     * Determine whether the user can view the list of models.
     *
     * @param $user
     *
     * @return bool
     */
    public function viewAny($user)
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param       $user
     * @param Model $model
     *
     * @return bool
     */
    public function view($user, Model $model)
    {
        return static::$model->is($model);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param $user
     *
     * @return bool
     */
    public function create($user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param       $user
     * @param Model $model
     *
     * @return bool
     */
    public function update($user, Model $model)
    {
        return static::$model->is($model);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param       $user
     * @param Model $model
     *
     * @return bool
     */
    public function delete($user, Model $model)
    {
        return static::$model->is($model);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param       $user
     * @param Model $model
     *
     * @return bool
     */
    public function restore($user, Model $model)
    {
        return static::$model->is($model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param       $user
     * @param Model $model
     *
     * @return bool
     */
    public function forceDelete($user, Model $model)
    {
        return static::$model->is($model);
    }
}
