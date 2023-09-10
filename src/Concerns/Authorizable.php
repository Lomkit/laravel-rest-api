<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Support\Facades\Gate;

trait Authorizable
{
    /**
     * Determine if the current user has a given ability.
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $ability
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     *
     * @return void
     */
    public function authorizeTo($ability, $model)
    {
        if ($this->isAuthorizingEnabled()) {
            Gate::authorize($ability, $model);
        }
    }

    /**
     * Determine if the current user can view the given resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $ability
     *
     * @return bool
     */
    public function authorizedTo($ability, $model)
    {
        if ($this->isAuthorizingEnabled()) {
            return Gate::check($ability, $model);
        }

        return true;
    }
}
