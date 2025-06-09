<?php

namespace Lomkit\Rest\Tests\Support\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class GreenPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the list of models.
     *
     * @param $user
     *
     * @return bool
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
     *
     * @return bool
     */
    public function view($user, Model $model)
    {
        return false;
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
        return true;
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
        return true;
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
        return true;
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
        return true;
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
        return true;
    }

    public function attachBelongsToRelation($user, Model $model)
    {
        return true;
    }

    public function attachHasOneRelation($user, Model $model)
    {
        return true;
    }

    public function attachHasOneOfManyRelation($user, Model $model)
    {
        return true;
    }

    public function attachBelongsToManyRelation($user, Model $model)
    {
        return true;
    }

    public function attachHasManyRelation($user, Model $model)
    {
        return true;
    }

    public function detachBelongsToRelation($user, Model $model)
    {
        return true;
    }

    public function detachHasOneRelation($user, Model $model)
    {
        return true;
    }

    public function detachHasOneOfManyRelation($user, Model $model)
    {
        return true;
    }

    public function detachBelongsToManyRelation($user, Model $model)
    {
        return true;
    }

    public function detachHasManyRelation($user, Model $model)
    {
        return true;
    }

    /**
     * MORPHS.
     */
    public function attachMorphToRelation($user, Model $model)
    {
        return true;
    }

    public function attachMorphOneRelation($user, Model $model)
    {
        return true;
    }

    public function attachMorphOneOfManyRelation($user, Model $model)
    {
        return true;
    }

    public function attachMorphToManyRelation($user, Model $model)
    {
        return true;
    }

    public function attachMorphManyRelation($user, Model $model)
    {
        return true;
    }

    public function attachMorphedByManyRelation($user, Model $model)
    {
        return true;
    }

    public function detachMorphToRelation($user, Model $model)
    {
        return true;
    }

    public function detachMorphOneRelation($user, Model $model)
    {
        return true;
    }

    public function detachMorphOneOfManyRelation($user, Model $model)
    {
        return true;
    }

    public function detachMorphToManyRelation($user, Model $model)
    {
        return true;
    }

    public function detachMorphManyRelation($user, Model $model)
    {
        return true;
    }

    public function detachMorphedByManyRelation($user, Model $model)
    {
        return true;
    }
}
