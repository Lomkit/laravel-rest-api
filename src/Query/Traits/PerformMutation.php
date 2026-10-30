<?php

namespace Lomkit\Rest\Query\Traits;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Query\AttributeWriter;

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
     * @param  array  $parameters  An array of mutation parameters.
     * @return array<string, mixed> An array containing 'created' and 'updated' operations with affected model keys.
     */
    public function mutate(array $parameters = [])
    {
        $operations = [
            'created' => [],
            'updated' => [],
        ];

        foreach ($parameters['mutate'] as $parameter) {
            $operations[$this->mutateOperationsVerbose[$parameter['operation']]][] = $this->applyMutation($parameter)->getKey();
        }

        return $operations;
    }

    /**
     * Apply a mutation to the model based on the provided mutation parameters.
     *
     * @param  array  $mutation  An array of mutation parameters.
     * @param  array  $attributes  The attributes owned by the parent relation, applied last.
     * @return Model The mutated model.
     */
    public function applyMutation(array $mutation = [], $attributes = [], $key = null)
    {
        if (MutationOperation::from($mutation['operation']) === MutationOperation::Create) {
            $this->resource->context()->authorizer()->ensurePlanned(Check::onClass($this->resource, 'create'));

            $model = $this->resource::newModel();
        } else {
            $model = $this->resource::newModel()::query()->findOrFail($key ?? $mutation['key']);

            $this->resource->authorizeTo($this->mutationAbility($mutation), $model);
        }

        return $this->mutateModel(
            $model,
            $mutation['attributes'] ?? [],
            $mutation,
            $attributes
        );
    }

    /**
     * Resolve the ability required to perform the given mutation.
     *
     * sync and toggle persist caller-supplied attributes onto the related model,
     * so they must authorize as an update rather than a view.
     *
     * @param  array  $mutation  An array of mutation parameters.
     * @return string The ability to authorize.
     */
    protected function mutationAbility(array $mutation)
    {
        $operation = MutationOperation::from($mutation['operation']);

        return match (true) {
            $operation === MutationOperation::Create, $operation === MutationOperation::Update => $operation->value,
            ! empty($mutation['attributes']) => $operation->writeOperation()->value ?? 'view',
            default => 'view',
        };
    }

    /**
     * Apply a mutation to the model based on the provided mutation parameters.
     *
     * @param  array  $mutation  An array of mutation parameters.
     * @param  array  $attributes  The attributes owned by the parent relation, applied last.
     * @return array<int, Model> The mutated model.
     */
    public function mutations(array $mutation = [], $attributes = [])
    {
        $keys = $mutation['key'] ?? [];
        $keys = is_array($keys) ? $keys : [$keys];

        return array_map(
            fn ($key) => $this->applyMutation($mutation, $attributes, $key),
            $keys
        );
    }

    /**
     * Mutate the model by applying attributes and relations.
     *
     * @param  Model  $model  The Eloquent model to mutate.
     * @param  array  $attributes  The attributes to mutate.
     * @param  array  $mutation  The mutation array.
     * @param  array<string, mixed>  $owned  The attributes owned by the parent relation, applied last.
     * @return Model The mutated model.
     */
    public function mutateModel(Model $model, array $attributes, array $mutation, array $owned = [])
    {
        /** @var MutateRequest $request */
        $request = $this->resource->context()->request;
        $relations = $mutation['relations'] ?? [];

        $restRelations = array_filter(
            $this->resource->getRelations($request),
            function ($relation) use ($relations) {
                return isset($relations[$relation->relation]);
            }
        );

        foreach ($restRelations as $restRelation) {
            $restRelation->beforeMutating($model, $restRelation, $relations);
        }

        $this
            ->resource
            ->mutating($request, $mutation, $model);

        $relationKeys = [];

        foreach ($restRelations as $restRelation) {
            $relationKeys = [...$relationKeys, ...$restRelation->ownedAttributes($model)];
        }

        (new AttributeWriter($this->resource->getFields($request)))
            ->write($model, MutationOperation::from($mutation['operation']), $attributes, [...$relationKeys, ...$owned])
            ->save();

        $this->resource->context()->authorizer()->forget($model);

        foreach ($restRelations as $restRelation) {
            $restRelation->afterMutating($model, $restRelation, $relations);
        }

        $this->resource->context()->authorizer()->forget($model);

        $this
            ->resource
            ->mutated($request, $mutation, $model);

        return $model;
    }
}
