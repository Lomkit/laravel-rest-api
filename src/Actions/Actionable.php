<?php

namespace Lomkit\Rest\Actions;

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
     * Check if a specific action exists.
     *
     * @param RestRequest $request
     * @param string $actionKey
     * @return bool
     */
    public function actionExists(RestRequest $request, string $actionKey): bool
    {
        return collect($this->actions($request))
            ->contains(function (Action $action) use ($actionKey) {
                return $action->uriKey() === $actionKey;
            });
    }

    /**
     * Get a specific action instance.
     *
     * @param RestRequest $request
     * @param string $actionKey
     * @return Action
     */
    public function action(RestRequest $request, string $actionKey): Action
    {
        return collect($this->actions($request))
            ->sole(function (Action $action) use ($actionKey) {
                return $action->uriKey() === $actionKey;
            })
            ->resource($this);
    }
}
