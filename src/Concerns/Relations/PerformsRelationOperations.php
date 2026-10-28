<?php

namespace Lomkit\Rest\Concerns\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Relations\Relation;

trait PerformsRelationOperations
{
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

        foreach ($results['attached'] as $attached) {
            $this->resource()->authorizeToAttach($model, $relation->resource()::$model::find($attached));
        }

        foreach ($results['detached'] as $detached) {
            $this->resource()->authorizeToDetach($model, $relation->resource()::$model::find($detached));
        }
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

        foreach ($results['attached'] as $attached) {
            $this->resource()->authorizeToAttach($model, $relation->resource()::$model::find($attached));
        }

        foreach ($results['detached'] as $detached) {
            $this->resource()->authorizeToDetach($model, $relation->resource()::$model::find($detached));
        }
    }
}
