<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Authorization\Check;

trait Authorizable
{
    public function authorizeTo(string $ability, Model|string $model): void
    {
        $this->authorizedTo($ability, $model)->authorize();
    }

    public function authorizedTo(string $ability, Model|string $model): Response
    {
        return $this->context()->authorizer()->inspect(new Check($this, $ability, $model))->toResponse();
    }

    public function authorizeToPerformActionOnRelationship(string $ability, Model $model, Model $toActionModel): void
    {
        $check = $this->relationshipCheck($ability, $model, $toActionModel);

        if ($check !== null) {
            $this->context()->authorizer()->authorize($check);
        }
    }

    public function authorizedToPerformActionOnRelationship(string $ability, Model $model, Model $toActionModel): bool
    {
        $check = $this->relationshipCheck($ability, $model, $toActionModel);

        return $check === null || $this->context()->authorizer()->inspect($check)->allowed;
    }

    public function authorizedToAttach(Model $model, Model $toAttachModel): bool
    {
        return $this->authorizedToPerformActionOnRelationship('attach', $model, $toAttachModel);
    }

    public function authorizeToAttach(Model $model, Model $toAttachModel): void
    {
        $this->authorizeToPerformActionOnRelationship('attach', $model, $toAttachModel);
    }

    public function authorizedToDetach(Model $model, Model $toAttachModel): bool
    {
        return $this->authorizedToPerformActionOnRelationship('detach', $model, $toAttachModel);
    }

    public function authorizeToDetach(Model $model, Model $toAttachModel): void
    {
        $this->authorizeToPerformActionOnRelationship('detach', $model, $toAttachModel);
    }

    private function relationshipCheck(string $ability, Model $model, Model $toActionModel): ?Check
    {
        $method = $ability.class_basename($toActionModel);
        $policy = Gate::getPolicyFor($model);

        if ($policy === null || ! method_exists($policy, $method)) {
            return null;
        }

        return new Check($this, $method, $model, related: [$toActionModel]);
    }
}
