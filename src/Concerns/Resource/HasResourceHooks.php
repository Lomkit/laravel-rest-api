<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Mutations\MutationNode;

trait HasResourceHooks
{
    public function mutating(MutationNode $entry, Model $model): void {}

    public function mutated(MutationNode $entry, Model $model): void {}

    public function destroying(Model $model): void {}

    public function destroyed(Model $model): void {}

    public function restoring(Model $model): void {}

    public function restored(Model $model): void {}

    public function forceDestroying(Model $model): void {}

    public function forceDestroyed(Model $model): void {}
}
