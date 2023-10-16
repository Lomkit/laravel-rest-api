<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Illuminate\Database\Eloquent\Model;

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
            $resolver = function () use ($ability, $model) {
                return Gate::authorize($ability, $model);
            };

            if ($this->isAuthorizationCacheEnabled()) {
                $gatePasses = Cache::remember(
                    $this->getAuthorizationCacheKey(
                        app(RestRequest::class),
                        sprintf(
                            '%s.%s.%s',
                            $ability,
                            $model instanceof Model ? Str::snake((new \ReflectionClass($model))->getShortName()) : $model,
                            $model instanceof Model ? $model->getKey() : null,
                        )
                    ),
                    $this->cacheAuthorizationFor(),
                    $resolver
                );
            } else {
                $gatePasses = $resolver();
            }

            if (!$gatePasses) {
                Response::deny()->authorize();
            }
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
            $resolver = function () use ($ability, $model) {
                return Gate::check($ability, $model);
            };

            if ($this->isAuthorizationCacheEnabled()) {
                return Cache::remember(
                    $this->getAuthorizationCacheKey(
                        app(RestRequest::class),
                        sprintf(
                            '%s.%s.%s',
                            $ability,
                            $model instanceof Model ? Str::snake((new \ReflectionClass($model))->getShortName()) : $model,
                            $model instanceof Model ? $model->getKey() : null,
                        )
                    ),
                    $this->cacheAuthorizationFor(),
                    $resolver
                );
            }

            return $resolver();
        }

        return true;
    }
}
