<?php

namespace Lomkit\Rest\Query\Traits;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Query\AttributeWriter;

trait PerformMutation
{
    /** @var array<string, string> */
    protected array $mutateOperationsVerbose = [
        'create' => 'created',
        'update' => 'updated',
    ];

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, list<int|string>>
     */
    public function mutate(array $parameters = []): array
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
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $attributes
     */
    public function applyMutation(array $mutation = [], array $attributes = [], int|string|null $key = null): Model
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

        return array_map(
            fn (int|string $key): Model => $this->applyMutation($mutation, $attributes, $key),
            $keys
        );
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
        $relations = $mutation['relations'] ?? [];

        $restRelations = array_filter(
            $this->resource->getRelations($request),
            fn ($relation): bool => isset($relations[$relation->relation])
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
