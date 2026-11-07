<?php

namespace Lomkit\Rest\Mutations;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\AttributeWriter;

final readonly class Mutator
{
    public function __construct(
        private RowSet $rows,
        public WriteChecks $checks,
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

    /**
     * @param  array<string, mixed>  $owned
     * @param  (Closure(Model): void)|null  $linking
     */
    public function one(Resource $resource, MutationNode $node, OperationLog $log, array $owned = [], ?Closure $linking = null): Model
    {
        $log->begin($node);
        $model = $log->wrote($this->row($resource, $node, $log, $owned, $node->context()->key, $linking));
        $log->end();

        return $model;
    }

    /**
     * @param  array<string, mixed>  $owned
     * @param  (Closure(Model): void)|null  $linking
     * @return list<Model>
     */
    public function each(Resource $resource, MutationNode $node, OperationLog $log, array $owned = [], ?Closure $linking = null): array
    {
        $keys = is_array($node->key) ? $node->key : [$node->key];

        $log->begin($node);
        $models = array_map(
            fn (int|string|null $key): Model => $log->wrote($this->row($resource, $node, $log, $owned, $key, $linking)),
            $keys
        );
        $log->end();

        return $models;
    }

    /**
     * @param  array<string, mixed>  $owned
     * @param  (Closure(Model): void)|null  $linking
     */
    private function row(Resource $resource, MutationNode $node, OperationLog $log, array $owned, int|string|null $key, ?Closure $linking): Model
    {
        $model = $node->operation === MutationOperation::Create ? $resource::newModel() : $this->rows->find($resource, $key);

        return $this->write($resource, $model, $node, $log, $owned, $linking);
    }

    /**
     * @param  array<string, mixed>  $owned
     * @param  (Closure(Model): void)|null  $linking
     */
    private function write(Resource $resource, Model $model, MutationNode $node, OperationLog $log, array $owned, ?Closure $linking): Model
    {
        $writes = RelationWrites::ofNode($resource, $model, $node);
        $hooked = self::writesAttributes($node);
        $relationKeys = [];

        $log->rewind();

        foreach ($writes as $write) {
            $log->within($write->relation);
            $write->before($model, $this, $log);
        }

        if ($hooked) {
            $resource->mutating($node, $model);
        }

        foreach ($writes as $write) {
            $relationKeys = [...$relationKeys, ...$write->owned($model)];
        }

        (new AttributeWriter($resource->definition()->fields()))->write($model, $node->operation, $node->attributes ?? [], [...$relationKeys, ...$owned]);

        if ($linking !== null) {
            $linking($model);
        }

        $this->checks->settle($resource, $model);

        if ($hooked || $model->isDirty()) {
            $model->save();
        }

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

        if ($hooked) {
            $resource->mutated($node, $model);
        }

        return $model;
    }

    private static function writesAttributes(MutationNode $node): bool
    {
        return $node->operation === MutationOperation::Create
            || $node->operation === MutationOperation::Update
            || ($node->attributes ?? []) !== [];
    }
}
