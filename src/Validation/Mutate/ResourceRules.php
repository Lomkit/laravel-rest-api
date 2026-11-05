<?php

namespace Lomkit\Rest\Validation\Mutate;

use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Fields\WriteRules;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

final class ResourceRules
{
    /** @var array<string, WriteRules> */
    private array $rules = [];

    /** @var array<string, array<array-key, mixed>> */
    private array $shared = [];

    /** @var array<string, WriteRules> */
    private array $pivots = [];

    public function __construct(private readonly RestRequest $request) {}

    public function for(Resource $resource, WriteOperation $operation): WriteRules
    {
        return $this->rules[$resource::class.':'.$operation->value] ??= WriteRules::of(
            $resource->getFields(),
            $operation,
            MorphTypeRules::of($resource, $operation)->addTo($operation === WriteOperation::Create ? $resource->createRules($this->request) : $resource->updateRules($this->request)),
            $this->shared($resource),
        );
    }

    public function forPivot(PivotRelation $relation, WriteOperation $operation): WriteRules
    {
        return $this->pivots[spl_object_id($relation).':'.$operation->value] ??= WriteRules::of($relation->getPivotFields(), $operation);
    }

    /** @return array<array-key, mixed> */
    private function shared(Resource $resource): array
    {
        return $this->shared[$resource::class] ??= $resource->rules($this->request);
    }
}
