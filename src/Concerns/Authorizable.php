<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;

trait Authorizable
{
    /**
     * Determine if the current user has a given ability.
     *
     * @param string       $ability
     * @param Model|string $model
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
     * Determine if the current user can perform an ability on the given model.
     *
     * @param string       $ability
     * @param Model|string $model
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

    /**
     * Determine if the current user has a given ability.
     *
     * @param string $ability
     *                        * @param Model $model
     *                        * @param string $toActionModel
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     *
     * @return void
     */
    public function authorizeToPerformActionOnRelationship($ability, $model, $toActionModel)
    {
        $gate = Gate::getPolicyFor($model);
        $method = $ability.class_basename($toActionModel);

        if (!is_null($gate) && method_exists($gate, $method) && $this->isAuthorizingEnabled()) {
            $resolver = function () use ($method, $gate, $model, $toActionModel) {
                return !is_null($gate) && method_exists($gate, $method)
                    ? Gate::authorize($method, [$model, $toActionModel])
                    : true;
            };

            if ($this->isAuthorizationCacheEnabled()) {
                $gatePasses = Cache::remember(
                    $this->getAuthorizationCacheKey(
                        app(RestRequest::class),
                        sprintf(
                            '%s.%s.%s.%s.%s',
                            $ability,
                            $model instanceof Model ? Str::snake((new \ReflectionClass($model))->getShortName()) : $model,
                            $model instanceof Model ? $model->getKey() : null,
                            $toActionModel instanceof Model ? Str::snake((new \ReflectionClass($toActionModel))->getShortName()) : $toActionModel,
                            $toActionModel instanceof Model ? $toActionModel->getKey() : null,
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
     * Determine if the current user can perform an ability on the given model.
     *
     * @param string $ability
     * @param Model  $model
     * @param string $toActionModel
     *
     * @return bool
     */
    public function authorizedToPerformActionOnRelationship($ability, $model, $toActionModel)
    {
        $gate = Gate::getPolicyFor($model);
        $method = $ability.class_basename($toActionModel);

        if (!is_null($gate) && method_exists($gate, $method) && $this->isAuthorizingEnabled()) {
            $resolver = function () use ($method, $toActionModel, $model) {
                return Gate::check($method, [$model, $toActionModel]);
            };

            if ($this->isAuthorizationCacheEnabled()) {
                return Cache::remember(
                    $this->getAuthorizationCacheKey(
                        app(RestRequest::class),
                        sprintf(
                            '%s.%s.%s.%s.%s',
                            $ability,
                            $model instanceof Model ? Str::snake((new \ReflectionClass($model))->getShortName()) : $model,
                            $model instanceof Model ? $model->getKey() : null,
                            $toActionModel instanceof Model ? Str::snake((new \ReflectionClass($toActionModel))->getShortName()) : $toActionModel,
                            $toActionModel instanceof Model ? $toActionModel->getKey() : null,
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

    /**
     * Determine if the user can attach models of the given type to the base model.
     *
     * @param \Illuminate\Database\Eloquent\Model|string $model
     * @param \Illuminate\Database\Eloquent\Model|string $toAttachModel
     *
     * @return bool
     */
    public function authorizedToAttach($model, $toAttachModel)
    {
        return $this->authorizedToPerformActionOnRelationship('attach', $model, $toAttachModel);
    }

    /**
     * Determine if the user can attach models of the given type to the base model.
     *
     * @param \Illuminate\Database\Eloquent\Model|string $model
     * @param \Illuminate\Database\Eloquent\Model|string $toAttachModel
     *
     * @return void
     */
    public function authorizeToAttach($model, $toAttachModel)
    {
        $this->authorizeToPerformActionOnRelationship('attach', $model, $toAttachModel);
    }
}
