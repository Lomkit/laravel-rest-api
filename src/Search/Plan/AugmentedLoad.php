<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Http\Resource;
use ReflectionMethod;
use ReflectionNamedType;

/** @internal */
final readonly class AugmentedLoad
{
    public const string SEPARATOR = '.';

    public static function ensureDeclared(string $extension, string $class, Resource $resource, string $path): void
    {
        $model = $resource::newModel();
        $segments = explode(self::SEPARATOR, $path);
        $last = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            $relation = self::declared($model, $segment) ?? throw InvalidQueryExtensionException::unknownRelation($extension, $class, $path, $resource::class);

            if ($relation instanceof MorphTo && $index < $last) {
                throw InvalidQueryExtensionException::loadThroughMorphTo($extension, $class, $path, $segment, $resource::class);
            }

            $model = $relation->getRelated();
        }
    }

    /** @return Relation<Model, Model, mixed>|null */
    private static function declared(Model $model, string $name): ?Relation
    {
        if (! method_exists($model, $name)) {
            return null;
        }

        $method = new ReflectionMethod($model, $name);
        $type = $method->getReturnType();

        if (! $method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0 || ! $type instanceof ReflectionNamedType || ! is_a($type->getName(), Relation::class, true)) {
            return null;
        }

        $relation = Relation::noConstraints(static fn (): mixed => $method->invoke($model));

        return $relation instanceof Relation ? $relation : null;
    }
}
