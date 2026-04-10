<?php

namespace Lomkit\Rest\Concerns\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Relations\Relation;

trait PerformsRelationOperations
{
    /**
     * @param Model    $model
     * @param Relation $relation
     * @param array    $mutation
     * @param array    $attributes
     *
     * @return void
     */
    public function create(Model $model, Relation $relation, array $mutation = [], array $attributes = [])
    {
        $toPerformActionModel = app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
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
     * @param Model    $model
     * @param Relation $relation
     * @param array    $mutation
     * @param array    $attributes
     *
     * @return void
     */
    public function update(Model $model, Relation $relation, array $mutation = [], array $attributes = [])
    {
        $toPerformActionModels = app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
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
     * @param Model    $model
     * @param Relation $relation
     * @param array    $mutation
     * @param array    $attributes
     *
     * @return void
     */
    public function attach(Model $model, Relation $relation, array $mutation = [], array $attributes = [])
    {
        $toPerformActionModels = app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
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
     * @param Model    $model
     * @param Relation $relation
     * @param array    $mutation
     * @param array    $attributes
     *
     * @return void
     */
    public function detach(Model $model, Relation $relation, array $mutation = [], array $attributes = [])
    {
        $toPerformActionModels = app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
            ->mutations($mutation, $attributes);

        foreach ($toPerformActionModels as $toPerformActionModel) {
            $this->resource()->authorizeToDetach($model, $toPerformActionModel);
        }

        $model->{$relation->relation}()->detach($toPerformActionModels);
    }

    /**
     * @param Model    $model
     * @param Relation $relation
     * @param array    $mutation
     * @param array    $attributes
     *
     * @return void
     */
    public function toggle(Model $model, Relation $relation, array $mutation = [], array $attributes = [])
    {
        $toPerformActionModels = app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
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
     * @param Model    $model
     * @param Relation $relation
     * @param array    $mutation
     * @param array    $attributes
     * @param bool     $withoutDetaching
     *
     * @return void
     */
    public function sync(Model $model, Relation $relation, array $mutation = [], array $attributes = [], $withoutDetaching = false)
    {
        $toPerformActionModels = app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
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
