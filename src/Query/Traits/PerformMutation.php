<?php

namespace Lomkit\Rest\Query\Traits;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformMutation
{
    /**
     * A map of verbose mutation operations to their actual operation names.
     *
     * @var array
     */
    protected $mutateOperationsVerbose = [
        'create' => 'created',
        'update' => 'updated',
    ];

    /**
     * Mutate the model based on the provided parameters.
     *
     * @param array $parameters An array of mutation parameters.
     *
     * @return array An array containing 'created' and 'updated' operations with affected model keys.
     */
    public function mutate(array $parameters = [])
    {
        $operations = [
            'created' => [],
            'updated' => [],
        ];

        foreach ($parameters['mutate'] as $parameter) {
            $operations[
                $this->mutateOperationsVerbose[$parameter['operation']]
            ][] = $this->applyMutation($parameter)->getKey();
        }

        return $operations;
    }

    /**
     * Apply a mutation to the model based on the provided mutation parameters.
     *
     * @param array $mutation   An array of mutation parameters.
     * @param array $attributes Additional attributes to apply to the model.
     *
     * @return Model The mutated model.
     */
    public function applyMutation(array $mutation = [], $attributes = [])
    {
        $allAttributes = array_merge($attributes, $mutation['attributes'] ?? []);

        if ($mutation['operation'] === 'create') {
            $model = $this->resource::newModel();

            $this->resource->authorizeTo('create', $model);

            return $this->mutateModel(
                $model,
                $allAttributes,
                $mutation['relations'] ?? []
            );
        }

        if ($mutation['operation'] === 'update') {
            $model = $this->resource::newModel()::find($mutation['key']);

            $this->resource->authorizeTo('update', $model);

            return $this->mutateModel(
                $model,
                $allAttributes,
                $mutation['relations'] ?? []
            );
        }

        $newModel = $this->resource::newModel()::find($mutation['key']);

        $newModel
            ->forceFill($allAttributes)
            ->save();

        return $newModel;
    }

    /**
     * Mutate the model by applying attributes and relations.
     *
     * @param Model $model      The Eloquent model to mutate.
     * @param array $attributes The attributes to apply to the model.
     * @param array $relations  The relations associated with the model.
     *
     * @return Model The mutated model.
     */
    public function mutateModel(Model $model, $attributes, $relations)
    {
        $restRelations = array_filter(
            $this->resource
                ->getRelations(
                    app()->make(RestRequest::class)
                ),
            function ($relation) use ($relations) {
                return isset($relations[$relation->relation]);
            }
        );

        foreach ($restRelations as $restRelation) {
            $restRelation->beforeMutating($model, $restRelation, $relations);
        }

        $model
            ->forceFill($attributes)
            ->save();

        foreach ($restRelations as $restRelation) {
            $restRelation->afterMutating($model, $restRelation, $relations);
        }

        return $model;
    }
}
