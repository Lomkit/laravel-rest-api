<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

final readonly class ModelRelations
{
    /** @return array<string, Relation<Model, Model, mixed>|Throwable> */
    public static function of(Model $model): array
    {
        $relations = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! self::declaresARelation($method)) {
                continue;
            }

            $relation = self::call($method, $model);

            if ($relation instanceof Relation || $relation instanceof Throwable) {
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

    private static function call(ReflectionMethod $method, Model $model): mixed
    {
        try {
            return Relation::noConstraints(fn (): mixed => $method->invoke($model));
        } catch (Throwable $exception) {
            return $exception;
        }
    }
}
