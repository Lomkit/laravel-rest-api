<?php

namespace Lomkit\Rest\Actions;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Actionable
{
    /**
     * The calculated actions if already done in this request.
     *
     * @var array
     */
    protected array $calculatedActions;

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
        return $this->calculatedActions ?? ($this->calculatedActions = $this->actions($request));
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
