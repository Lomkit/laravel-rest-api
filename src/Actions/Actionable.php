<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Support\Facades\Cache;
use Lomkit\Rest\Http\Requests\RestRequest;

trait Actionable
{
    /**
     * The actions that should be linked.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function actions(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the resource's actions.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     *
     * @return array
     */
    public function getActions(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        $resolver = function () use ($request) {
            return $this->actions($request);
        };

        if ($this->isCachingEnabled()) {
            return Cache::remember(
                $this->getCacheKey($request, 'actions'),
                $this->cacheFor(),
                $resolver
            );
        }

        return $resolver();
    }

    /**
     * Check if a specific action exists.
     *
     * @param RestRequest $request
     * @param string      $actionKey
     *
     * @return bool
     */
    public function actionExists(RestRequest $request, string $actionKey): bool
    {
        return collect($this->getActions($request))
            ->contains(function (Action $action) use ($actionKey) {
                return $action->uriKey() === $actionKey;
            });
    }

    /**
     * Get a specific action instance.
     *
     * @param RestRequest $request
     * @param string      $actionKey
     *
     * @return Action
     */
    public function action(RestRequest $request, string $actionKey): Action
    {
        return collect($this->getActions($request))
            ->sole(function (Action $action) use ($actionKey) {
                return $action->uriKey() === $actionKey;
            })
            ->resource($this);
    }
}
