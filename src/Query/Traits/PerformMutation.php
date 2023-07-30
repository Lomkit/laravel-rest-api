<?php

namespace Lomkit\Rest\Query\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasOne;

trait PerformMutation
{

    protected $mutateOperationsVerbose = [
        'create' => 'created',
        'update' => 'updated'
    ];

    public function mutate(array $parameters = []) {
        $operations = [
            'created' => [],
            'updated' => []
        ];

        foreach ($parameters['mutate'] as $parameter) {
            $operations[
                $this->mutateOperationsVerbose[$parameter['operation']]
            ][] = $this->applyMutation($parameter)->getKey();
        }

        return $operations;
    }

    public function applyMutation(array $mutation = [], $attributes = []) {
        $allAttributes = array_merge($attributes, $mutation['attributes'] ?? []);

        if ($mutation['operation'] === 'create') {
            $model = $this->resource::newModel();

            $this->authorizeTo('create', $model);

            return $this->mutateModel(
                $model,
                $allAttributes,
                    $mutation['relations'] ?? []
            );
        }

        if ($mutation['operation'] === 'update') {
            $model = $this->resource::newModel()::find($mutation['key']);

            $this->authorizeTo('update', $model);

            return $this->mutateModel(
                $model,
                $allAttributes,
                $mutation['relations'] ?? []
            );
        }

        $newModel = $this->resource::newModel()
            ::find($mutation['key']);

        $newModel
            ->forceFill($allAttributes)
            ->save();

        return $newModel;
    }

    public function mutateModel(Model $model, $attributes, $relations) {
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