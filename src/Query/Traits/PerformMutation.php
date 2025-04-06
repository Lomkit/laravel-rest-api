<?php

namespace Lomkit\Rest\Query\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\MutateRequest;
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
            $model = $this->applyMutation($parameter);
            $operations[$this->mutateOperationsVerbose[$parameter['operation']]][] = $this->formatModelResponse($model);
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
        } else {
            $model = $this->resource::newModel()::findOrFail($mutation['key']);
        }

        if ($mutation['operation'] === 'create') {
            $this->resource->authorizeTo('create', $model);
        } elseif ($mutation['operation'] === 'update') {
            $this->resource->authorizeTo('update', $model);
        } else {
            $this->resource->authorizeTo('view', $model);
        }

        return $this->mutateModel(
            $model,
            $allAttributes,
            $mutation
        );
    }

    /**
     * Mutate the model by applying attributes and relations.
     *
     * @param Model $model      The Eloquent model to mutate.
     * @param array $attributes The attributes to mutate.
     * @param array $mutation   The mutation array.
     *
     * @return Model The mutated model.
     */
    public function mutateModel(Model $model, array $attributes, array $mutation)
    {
        $relations = $mutation['relations'] ?? [];

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

        $this
            ->resource
            ->mutating(app(MutateRequest::class), $mutation, $model);

        $model
            ->forceFill($attributes)
            ->save();

        foreach ($restRelations as $restRelation) {
            $restRelation->afterMutating($model, $restRelation, $relations);
        }

        $this
            ->resource
            ->mutated(app(MutateRequest::class), $mutation, $model);

        return $model;
    }

    /**
     * Modify the response according to $responseFields in the resource.
     *
     * @param Model $model
     *
     * @return mixed|array
     */
    public function formatModelResponse(Model $model)
    {
        $fields = $this->resource->responseFields ?? [];
        if (empty($fields)) {
            return $model->getKey();
        }
        $attributes = collect($model->getAttributes())->only($fields);
        $relationsInResource = $this->resource->getRelations(app()->make(RestRequest::class));
        $relationsInModel = $model->getRelations();
        $formattedRelations = [];
        $defaultRelations = $this->resource->responseRelations ?? [];
        foreach ($defaultRelations as $relationName) {
            if (!array_key_exists($relationName, $relationsInModel)) {
                continue;
            }

            $relation = collect($relationsInResource)->first(function ($rel) use ($relationName) {
                return Str::singular($rel->resource()::newModel()->getTable()) === $relationName;
            });

            if (!$relation) {
                continue;
            }

            $relationFields = $relation->resource()->responseFields ?? [];
            if (empty($relationFields)) {
                continue;
            }

            $relationModel = $relationsInModel[$relationName];

            if ($relationModel instanceof Collection) {
                $formattedRelations[$relationName] = $relationModel->map(function ($model) use ($relationFields) {
                    return collect($model->getAttributes())->only($relationFields);
                })->toArray();
            } else {
                $formattedRelations[$relationName] = collect($relationModel->getAttributes())->only($relationFields);
            }
        }
        $result = $attributes->toArray();
        foreach ($formattedRelations as $relation => $value) {
            $result[$relation] = $value;
        }

        return $result;
    }
}
