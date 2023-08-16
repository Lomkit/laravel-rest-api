<?php

namespace Lomkit\Rest\Actions;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Actionable
{
    /**
     * The actions that should be linked
     * @param RestRequest $request
     * @return array
     */
    public function actions(RestRequest $request): array {
        return [];
    }

    public function actionExists(RestRequest $request, string $actionKey): bool {
        return collect($this->actions($request))
            ->contains(function (Action $action) use ($actionKey) {
                return $action->uriKey() === $actionKey;
            });
    }

    public function action(RestRequest $request, string $actionKey): Action {
        return collect($this->actions($request))
            ->sole(function (Action $action) use ($actionKey) {
                return $action->uriKey() === $actionKey;
            })
            ->resource($this);
    }
}