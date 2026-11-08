<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Relations\Relation;

final readonly class ReachedResources
{
    public function __construct(private RestContext $context) {}

    /**
     * @param  list<class-string<Resource>>  $roots
     * @return list<class-string<Resource>>
     */
    public function from(array $roots): array
    {
        $reached = $roots;

        for ($index = 0; $index < count($reached); $index++) {
            foreach ($this->related($reached[$index]) as $related) {
                if (! in_array($related, $reached, true)) {
                    $reached[] = $related;
                }
            }
        }

        return $reached;
    }

    /**
     * @param  class-string<Resource>  $resource
     * @return list<class-string<Resource>>
     */
    private function related(string $resource): array
    {
        $definition = $this->context->resource($resource)->definition();

        $related = array_map(
            fn (Relation $relation): array => $relation instanceof PolymorphicRelation ? $relation->targets()->resources() : [$relation->resource()::class],
            $definition->relations(),
        );

        return array_merge(...array_values($related));
    }
}
