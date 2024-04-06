<?php

namespace Lomkit\Rest\Tests\Support\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class NoRelationshipAuthorizationModelPolicy
{
    use HandlesAuthorization;

    public function attachBelongsToRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function attachHasOneRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function attachHasOneOfManyRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function attachBelongsToManyRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function attachHasManyRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function detachBelongsToRelation($user, Model $model, Model $toDetachModel)
    {
        return false;
    }

    public function detachHasOneRelation($user, Model $model, Model $toDetachModel)
    {
        return false;
    }

    public function detachHasOneOfManyRelation($user, Model $model, Model $toDetachModel)
    {
        return false;
    }

    public function detachBelongsToManyRelation($user, Model $model, Model $toDetachModel)
    {
        return false;
    }

    public function detachHasManyRelation($user, Model $model, Model $toDetachModel)
    {
        return false;
    }

    /**
     * MORPHS.
     */
    public function attachMorphToRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function attachMorphOneRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function attachMorphOneOfManyRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function attachMorphToManyRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function attachMorphManyRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function detachMorphToRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function detachMorphOneRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function detachMorphOneOfManyRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function detachMorphToManyRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
    }

    public function detachMorphManyRelation($user, Model $model, Model $toAttachModel)
    {
        return false;
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
        return true;
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
}
