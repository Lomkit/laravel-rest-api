<?php

namespace Lomkit\Rest\Actions;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Actionable
{
    /** @return array<int, Action> */
    public function actions(RestRequest $request): array
    {
        return [];
    }

    /** @return array<int, Action> */
    public function getActions(RestRequest $request): array
    {
        return $this->context()->definition($this)->actions();
    }

    /**
     * Check if a specific action exists.
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
