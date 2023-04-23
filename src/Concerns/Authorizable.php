<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

trait Authorizable
{
    /**
     * Determine if the current user has a given ability.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $ability
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorizeTo($ability, $model)
    {
        Gate::authorize($ability, $model);
    }

    /**
     * Determine if the current user can view the given resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $ability
     * @return bool
     */
    public function authorizedTo($ability, $model)
    {
        return Gate::check($ability, $model);
    }
}