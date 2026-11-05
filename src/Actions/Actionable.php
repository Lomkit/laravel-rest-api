<?php

namespace Lomkit\Rest\Actions;

trait Actionable
{
    /** @return array<int, Action> */
    public function actions(): array
    {
        return [];
    }

    /** @return array<int, Action> */
    public function getActions(): array
    {
        return $this->context()->definition($this)->actions();
    }

    /**
     * Check if a specific action exists.
     */
    public function actionExists(string $actionKey): bool
    {
        return collect($this->getActions())
            ->contains(function (Action $action) use ($actionKey) {
                return $action->uriKey() === $actionKey;
            });
    }

    /**
     * Get a specific action instance.
     */
    public function action(string $actionKey): Action
    {
        return collect($this->getActions())
            ->sole(function (Action $action) use ($actionKey) {
                return $action->uriKey() === $actionKey;
            })
            ->resource($this);
    }
}
