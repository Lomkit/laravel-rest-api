<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Relations\Relation;
use Throwable;

final readonly class ReachedResources
{
    public function __construct(private RestContext $context) {}

    /**
     * @param  list<class-string<Resource>>  $roots
     * @return list<class-string<Resource>>
     */
    public function from(array $roots): array
    {
        return $this->reach($roots)->resources;
    }

    /** @param list<class-string<Resource>> $roots */
    public function reach(array $roots): ResourceReach
    {
        $queued = $roots;
        $reached = [];
        $unbuilt = new UnbuiltResources;

        for ($index = 0; $index < count($queued); $index++) {
            $resource = $queued[$index];

            try {
                $built = $this->context->resource($resource);
            } catch (Throwable $exception) {
                $unbuilt->add($resource, $exception);

                continue;
            }

            $reached[] = $resource;

            foreach ($built->definition()->relations() as $relation) {
                foreach (self::related($resource, $relation, $unbuilt) as $class) {
                    if (! in_array($class, $queued, true)) {
                        $queued[] = $class;
                    }
                }
            }
        }

        return new ResourceReach($reached, $unbuilt->problems());
    }

    /**
     * @param  class-string<Resource>  $resource
     * @return list<class-string<Resource>>
     */
    private static function related(string $resource, Relation $relation, UnbuiltResources $unbuilt): array
    {
        if ($relation instanceof PolymorphicRelation) {
            return $relation->targets()->resources();
        }

        try {
            return [$relation->resource()::class];
        } catch (Throwable $exception) {
            $unbuilt->add($relation->resourceClass(), $exception, "{$resource} [{$relation->relation}]");

            return [];
        }
    }
}
