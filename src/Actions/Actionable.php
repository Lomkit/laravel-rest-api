<?php

namespace Lomkit\Rest\Actions;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Actionable
{
    /**
     * The calculated actions if already done in this request.
     */
    protected array $calculatedActions;

    /**
     * The actions that should be linked.
     */
    public function actions(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the resource's actions.
     */
    public function getActions(RestRequest $request): array
    {
        return $this->calculatedActions ??= array_map(fn (Action $action) => $action->resource($this), $this->actions($request));
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
