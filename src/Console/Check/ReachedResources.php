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
        return $this->walk($roots)['reached'];
    }

    /**
     * @param  list<class-string<Resource>>  $roots
     * @return list<string>
     */
    public function problems(array $roots): array
    {
        return $this->walk($roots)['problems'];
    }

    /**
     * @param  list<class-string<Resource>>  $roots
     * @return array{reached: list<class-string<Resource>>, problems: list<string>}
     */
    private function walk(array $roots): array
    {
        $queued = $roots;
        $reached = [];
        $problems = [];

        for ($index = 0; $index < count($queued); $index++) {
            $resource = $queued[$index];

            try {
                $built = $this->context->resource($resource);
            } catch (Throwable $exception) {
                $problems[] = "{$resource} cannot be built, so no other check reads it: {$exception->getMessage()}";

                continue;
            }

            $reached[] = $resource;

            foreach ($built->definition()->relations() as $relation) {
                try {
                    $related = self::related($relation);
                } catch (Throwable $exception) {
                    $problems[] = "{$resource} [{$relation->relation}]: the resource of the relation cannot be built, so no other check reads it: {$exception->getMessage()}";

                    continue;
                }

                foreach ($related as $class) {
                    if (! in_array($class, $queued, true)) {
                        $queued[] = $class;
                    }
                }
            }
        }

        return ['reached' => $reached, 'problems' => $problems];
    }

    /** @return list<class-string<Resource>> */
    private static function related(Relation $relation): array
    {
        return $relation instanceof PolymorphicRelation ? $relation->targets()->resources() : [$relation->resource()::class];
    }
}
