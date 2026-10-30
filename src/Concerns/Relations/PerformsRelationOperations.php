<?php

namespace Lomkit\Rest\Concerns\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\Relation;

trait PerformsRelationOperations
{
    /** @return list<MutationOperation> */
    public function mutationOperations(): array
    {
        return MutationOperation::cases();
    }

    /**
     * @return void
     */
    public function create(Model $model, Relation $relation, array $mutation = [], array $attributes = [])
    {
        $toPerformActionModel = app()->make(EloquentQueryBuilder::class, ['resource' => $relation->resource()])
            ->applyMutation($mutation, $attributes);

        $this->resource()->authorizeToAttach($model, $toPerformActionModel);

        $model
            ->{$relation->relation}()
            ->syncWithoutDetaching(
                [
                    $toPerformActionModel->getKey() => $mutation['pivot'] ?? [],
                ]
            );

        $this->resource()->context()->authorizer()->forget($model);
    }

    /**
     * @return void
     */
    public function update(Model $model, Relation $relation, array $mutation = [], array $attributes = [])
    {
        $toPerformActionModels = app()->make(EloquentQueryBuilder::class, ['resource' => $relation->resource()])
            ->mutations($mutation, $attributes);

        foreach ($toPerformActionModels as $toPerformActionModel) {
            $this->resource()->authorizeToAttach($model, $toPerformActionModel);
        }

        $model->{$relation->relation}()
            ->syncWithoutDetaching(
                collect($toPerformActionModels)
                    ->mapWithKeys(
                        fn (Model $toPerformActionModel) => [$toPerformActionModel->getKey() => $mutation['pivot'] ?? []]
                    )->toArray()
            );

        $this->resource()->context()->authorizer()->forget($model);
    }

    /**
     * @return void
     */
    public function attach(Model $model, Relation $relation, array $mutation = [], array $attributes = [])
    {
        $toPerformActionModels = app()->make(EloquentQueryBuilder::class, ['resource' => $relation->resource()])
            ->mutations($mutation, $attributes);

        foreach ($toPerformActionModels as $toPerformActionModel) {
            $this->resource()->authorizeToAttach($model, $toPerformActionModel);
        }

        $model->{$relation->relation}()
            ->attach(
                collect($toPerformActionModels)
                    ->mapWithKeys(
                        fn (Model $toPerformActionModel) => [$toPerformActionModel->getKey() => $mutation['pivot'] ?? []]
                    )->toArray()
            );

        $this->resource()->context()->authorizer()->forget($model);
    }

    /**
     * @return void
     */
    public function detach(Model $model, Relation $relation, array $mutation = [], array $attributes = [])
    {
        $toPerformActionModels = app()->make(EloquentQueryBuilder::class, ['resource' => $relation->resource()])
            ->mutations($mutation, $attributes);

        foreach ($toPerformActionModels as $toPerformActionModel) {
            $this->resource()->authorizeToDetach($model, $toPerformActionModel);
        }

        $model->{$relation->relation}()->detach($toPerformActionModels);

        $this->resource()->context()->authorizer()->forget($model);
    }

    /**
     * @return void
     */
    public function toggle(Model $model, Relation $relation, array $mutation = [], array $attributes = [])
    {
        $toPerformActionModels = app()->make(EloquentQueryBuilder::class, ['resource' => $relation->resource()])
            ->mutations($mutation, $attributes);

        $results = $model->{$relation->relation}()
            ->toggle(
                collect($toPerformActionModels)
                    ->mapWithKeys(
                        fn (Model $toPerformActionModel) => [$toPerformActionModel->getKey() => $mutation['pivot'] ?? []]
                    )->toArray(),
            );

        $this->resource()->context()->authorizer()->forget($model);

        $this->authorizeChanges($model, $relation, $results);
    }

    /**
     * @param  bool  $withoutDetaching
     * @return void
     */
    public function sync(Model $model, Relation $relation, array $mutation = [], array $attributes = [], $withoutDetaching = false)
    {
        $toPerformActionModels = app()->make(EloquentQueryBuilder::class, ['resource' => $relation->resource()])
            ->mutations($mutation, $attributes);

        $results = $model->{$relation->relation}()
            ->sync(
                collect($toPerformActionModels)
                    ->mapWithKeys(
                        fn (Model $toPerformActionModel) => [$toPerformActionModel->getKey() => $mutation['pivot'] ?? []]
                    )->toArray(),
                $withoutDetaching
            );

        $this->resource()->context()->authorizer()->forget($model);

        $this->authorizeChanges($model, $relation, $results);
    }

    /** @param array{attached: list<int|string>, detached: list<int|string>} $changes */
    private function authorizeChanges(Model $model, Relation $relation, array $changes): void
    {
        foreach ($changes['attached'] as $attached) {
            $related = $relation->resource()::modelClass()::find($attached);

            if ($related instanceof Model) {
                $this->resource()->authorizeToAttach($model, $related);
            }
        }

        foreach ($changes['detached'] as $detached) {
            $related = $relation->resource()::modelClass()::find($detached);

            if ($related instanceof Model) {
                $this->resource()->authorizeToDetach($model, $related);
            }
        }
    }
}
