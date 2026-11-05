<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\AttributeWriter;

final readonly class Mutator
{
    public function __construct(
        private MutateRequest $request,
        private RowLookup $rows = new RowLookup,
    ) {}

    /**
     * @param  list<MutationNode>  $nodes
     * @return list<OperationEntry>
     */
    public function execute(Resource $resource, array $nodes): array
    {
        $log = new OperationLog;

        foreach ($nodes as $node) {
            $this->one($resource, $node, $log);
        }

        return $log->entries();
    }

    /** @param array<string, mixed> $owned */
    public function one(Resource $resource, MutationNode $node, OperationLog $log, array $owned = []): Model
    {
        $log->begin($node);
        $model = $log->wrote($this->row($resource, $node, $log, $owned, $node->context()->key));
        $log->end();

        return $model;
    }

    /**
     * @param  array<string, mixed>  $owned
     * @return list<Model>
     */
    public function each(Resource $resource, MutationNode $node, OperationLog $log, array $owned = []): array
    {
        $keys = is_array($node->key) ? $node->key : [$node->key];

        $log->begin($node);
        $models = array_map(
            fn (int|string|null $key): Model => $log->wrote($this->row($resource, $node, $log, $owned, $key)),
            $keys
        );
        $log->end();

        return $models;
    }

    /** @param array<string, mixed> $owned */
    private function row(Resource $resource, MutationNode $node, OperationLog $log, array $owned, int|string|null $key): Model
    {
        if ($node->operation === MutationOperation::Create) {
            $resource
                ->context()
                ->authorizer()
                ->ensurePlanned(Check::onClass($resource, 'create'));

            return $this->write($resource, $resource::newModel(), $node, $log, $owned);
        }

        $model = $this->rows->find($resource, $key);
        $resource->authorizeTo(self::ability($node), $model);

        return $this->write($resource, $model, $node, $log, $owned);
    }

    /** @param array<string, mixed> $owned */
    private function write(Resource $resource, Model $model, MutationNode $node, OperationLog $log, array $owned): Model
    {
        $entry = $node->toArray();
        $writes = self::writes($resource, $model, $node);
        $relationKeys = [];

        $log->rewind();

        foreach ($writes as $write) {
            $log->within($write->relation);
            $write->before($model, $this, $log);
        }

        $resource->mutating($this->request, $entry, $model);

        foreach ($writes as $write) {
            $relationKeys = [...$relationKeys, ...$write->owned($model)];
        }

        (new AttributeWriter($resource->definition()->fields()))
            ->write($model, $node->operation, $node->attributes ?? [], [...$relationKeys, ...$owned])
            ->save();

        if ($model->wasRecentlyCreated || $model->wasChanged()) {
            $resource
                ->context()
                ->authorizer()
                ->forget($model);
        }

        foreach ($writes as $write) {
            $log->within($write->relation);
            $write->after($model, $this, $log);
        }

        if ($writes !== []) {
            $resource
                ->context()
                ->authorizer()
                ->forget($model);
        }

        $resource->mutated($this->request, $entry, $model);

        return $model;
    }

    /** @return list<RelationWrites> */
    private static function writes(Resource $resource, Model $model, MutationNode $node): array
    {
        $entries = $node->relations ?? [];
        $writes = [];

        foreach ($resource->definition()->relations() as $relation) {
            if (isset($entries[$relation->relation])) {
                $writes[] = RelationWrites::of($model, $relation, $entries[$relation->relation]);
            }
        }

        return $writes;
    }

    private static function ability(MutationNode $node): string
    {
        return match (true) {
            $node->operation === MutationOperation::Create, $node->operation === MutationOperation::Update => $node->operation->value,
            ! empty($node->attributes) => $node->operation->writeOperation()->value ?? 'view',
            default => 'view',
        };
    }
}
