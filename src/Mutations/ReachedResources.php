<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

/** @internal */
final class ReachedResources
{
    /** @var array<class-string<Resource>, Resource> */
    private array $resources = [];

    /** @var array<class-string<Resource>, array<int, MutationNode>> */
    private array $nodes = [];

    /** @var array<class-string<Resource>, array<int, Model>> */
    private array $rows = [];

    /** @param list<Model> $rows */
    public function add(Resource $resource, MutationNode $node, array $rows): void
    {
        $this->resources[$resource::class] ??= $resource;
        $this->nodes[$resource::class][spl_object_id($node)] = $node;
        $this->rows[$resource::class] ??= [];

        foreach ($rows as $row) {
            if ($row->exists) {
                $this->rows[$resource::class][spl_object_id($row)] = $row;
            }
        }
    }

    /** @return list<array{Resource, list<MutationNode>, list<Model>}> */
    public function all(): array
    {
        $reached = [];

        foreach ($this->resources as $class => $resource) {
            $reached[] = [$resource, array_values($this->nodes[$class] ?? []), array_values($this->rows[$class] ?? [])];
        }

        return $reached;
    }
}
