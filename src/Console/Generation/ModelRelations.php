<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final readonly class ModelRelations
{
    /** @return array<string, Relation<Model, Model, mixed>> */
    public static function of(Model $model): array
    {
        $relations = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! self::declaresARelation($method)) {
                continue;
            }

            $relation = Relation::noConstraints(fn (): mixed => $method->invoke($model));

            if ($relation instanceof Relation) {
                $relations[$method->getName()] = $relation;
            }
        }

        return $relations;
    }

    private static function declaresARelation(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        return ! $method->isStatic()
            && $method->getNumberOfRequiredParameters() === 0
            && $type instanceof ReflectionNamedType
            && is_a($type->getName(), Relation::class, true);
    }
}
