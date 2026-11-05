<?php

namespace Lomkit\Rest\Query\Traits;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Mutations\OperationEntry;
use Lomkit\Rest\Query\AttributeWriter;

trait PerformMutation
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return list<OperationEntry>
     */
    public function mutate(array $parameters = []): array
    {
        $operations = $this->resource->context()->operations();
        $operations->start();

        foreach ($parameters['mutate'] as $parameter) {
            $this->applyMutation($parameter);
        }

        return $operations->entries();
    }

    /**
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $attributes
     */
    public function applyMutation(array $mutation = [], array $attributes = [], int|string|null $key = null): Model
    {
        $operations = $this->resource->context()->operations();

        $operations->begin($key === null ? $mutation : [...$mutation, 'key' => $key]);
        $model = $operations->wrote($this->writeMutation($mutation, $attributes, $key));
        $operations->end();

        return $model;
    }

    /**
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $attributes
     */
    private function writeMutation(array $mutation, array $attributes, int|string|null $key): Model
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

    /** @param array<string, mixed> $mutation */
    protected function mutationAbility(array $mutation): string
    {
        $operation = MutationOperation::from($mutation['operation']);

        return match (true) {
            $operation === MutationOperation::Create, $operation === MutationOperation::Update => $operation->value,
            ! empty($mutation['attributes']) => $operation->writeOperation()->value ?? 'view',
            default => 'view',
        };
    }

    /**
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $attributes
     * @return list<Model>
     */
    public function mutations(array $mutation = [], array $attributes = []): array
    {
        $keys = $mutation['key'] ?? [];
        $keys = is_array($keys) ? array_values($keys) : [$keys];
        $operations = $this->resource->context()->operations();

        $operations->begin($mutation);
        $models = array_map(
            fn (int|string $key): Model => $operations->wrote($this->writeMutation($mutation, $attributes, $key)),
            $keys
        );
        $operations->end();

        return $models;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $owned
     */
    public function mutateModel(Model $model, array $attributes, array $mutation, array $owned = []): Model
    {
        /** @var MutateRequest $request */
        $request = $this->resource->context()->request;
        $operations = $this->resource->context()->operations();
        $relations = $mutation['relations'] ?? [];

        $restRelations = array_filter(
            $this->resource->getRelations(),
            fn ($relation): bool => isset($relations[$relation->relation])
        );

        $operations->rewind();

        foreach ($restRelations as $restRelation) {
            $operations->within($restRelation);
            $restRelation->beforeMutating($model, $restRelation, $relations);
        }

        $this
            ->resource
            ->mutating($request, $mutation, $model);

        $relationKeys = [];

        foreach ($restRelations as $restRelation) {
            $relationKeys = [...$relationKeys, ...$restRelation->ownedAttributes($model)];
        }

        (new AttributeWriter($this->resource->getFields()))
            ->write($model, MutationOperation::from($mutation['operation']), $attributes, [...$relationKeys, ...$owned])
            ->save();

        if ($model->wasRecentlyCreated || $model->wasChanged()) {
            $this->resource->context()->authorizer()->forget($model);
        }

        foreach ($restRelations as $restRelation) {
            $operations->within($restRelation);
            $restRelation->afterMutating($model, $restRelation, $relations);
        }

        if ($restRelations !== []) {
            $this->resource->context()->authorizer()->forget($model);
        }

        $this
            ->resource
            ->mutated($request, $mutation, $model);

        return $model;
    }
}
