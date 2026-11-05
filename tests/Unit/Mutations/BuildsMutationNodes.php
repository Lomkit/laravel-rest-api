<?php

namespace Lomkit\Rest\Tests\Unit\Mutations;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Mutations\MutationNode;

trait BuildsMutationNodes
{
    /**
     * @param  int|string|list<int|string>|null  $key
     * @param  array<string, mixed>|null  $attributes
     * @param  array<string, MutationNode|list<MutationNode>>|null  $relations
     */
    private static function node(string $operation, int|string|array|null $key = null, ?array $attributes = null, ?bool $withoutDetaching = null, ?array $relations = null): MutationNode
    {
        return new MutationNode(MutationOperation::from($operation), $key, $attributes, null, $withoutDetaching, $relations, 1);
    }
}
