<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidResourceException extends LogicException
{
    public static function missingModel(string $resource): self
    {
        return new self("The resource [{$resource}] does not declare its model. Add public static string \$model = YourModel::class; to it.");
    }

    public static function versionWithoutTimestamps(string $resource): self
    {
        return new self("The resource [{$resource}] returns true from versioned(), but its model does not use timestamps. The version of a row is its updated_at column: turn the timestamps of the model on, or return false from versioned().");
    }

    public static function versionNotAField(string $resource, string $column): self
    {
        return new self("The resource [{$resource}] returns true from versioned(), but does not declare the field [{$column}]. The version of a row is this column as the field returns it: declare it, for example DateTime::make('{$column}')->readonly().");
    }

    public static function gatesKeyField(string $resource, string $field): self
    {
        return new self("The resource [{$resource}] declares the field [{$field}], which is the key of the gates of its rows. Rename the field, or set rest.gates.key to another name.");
    }

    public static function reservedSearchMetaKey(string $resource, string $key): self
    {
        return new self("The resource [{$resource}] returns the key [{$key}] from searchMeta(), which the package fills in the meta of a search page. Rename the key.");
    }

    public static function moreKeyField(string $resource, string $field): self
    {
        return new self("The resource [{$resource}] declares the field [{$field}], which is the key that lists the includes of a row with more rows. Rename the field, or set rest.search.more_key to another name.");
    }

    /** @param list<int> $allowed */
    public static function limitNotAllowed(string $resource, string $property, int $limit, array $allowed): self
    {
        $limits = implode(', ', $allowed);

        return new self("The resource [{$resource}] sets \${$property} to {$limit}, which is not one of its limits() [{$limits}]. Return {$limit} from limits(), or set \${$property} to one of them.");
    }

    public static function maxTotalTooLow(string $resource, int $maxTotal, int $floor): self
    {
        return new self("The resource [{$resource}] sets \$maxTotal to {$maxTotal}, which is below its largest limit {$floor}. Set \$maxTotal to {$floor} or more, or to null to count every row.");
    }

    public static function invalidGate(string $owner, int $index, mixed $gate): self
    {
        if (! is_string($gate)) {
            return new self("{$owner} returned ".get_debug_type($gate)." at index {$index}. Return the names of policy methods.");
        }

        return new self("{$owner} declares the gate [{$gate}] at index {$index}. A declared gate is the camelCase name of a policy method, listed once, other than viewAny and the built-in gates view, create, update, delete, restore and forceDelete.");
    }

    public static function invalidCachedAbility(string $resource, int|string $key): self
    {
        return new self("The resource [{$resource}] returns the key [{$key}] from authorizationCache(). Key each number of seconds by the name of an ability, for example ['viewAny' => 300].");
    }

    public static function invalidCacheDuration(string $resource, string $ability): self
    {
        return new self("The resource [{$resource}] caches the ability [{$ability}] in authorizationCache() without a valid duration. Give an integer number of seconds of 1 or more, for example ['{$ability}' => 300].");
    }

    public static function invalidCacheContext(string $resource, int|string $key): self
    {
        return new self("The resource [{$resource}] returns a value for the key [{$key}] from authorizationCacheContext() that is not an integer, a string, a boolean or null. Give a scalar value, for example ['team' => 1].");
    }

    public static function notAMorphTo(string $resource, string $relation): self
    {
        return new self("The relation [{$relation}] of the resource [{$resource}] is declared with MorphTo, but its model method does not return a MorphTo relation.");
    }

    public static function morphRelationName(string $resource, string $relation, string $name): self
    {
        return new self("The relation [{$relation}] of the resource [{$resource}] is declared with MorphTo, but its model method returns the MorphTo relation [{$name}]. Declare the relation under the name of the model method that defines it.");
    }

    public static function invalidMorphTargets(string $resource, string $relation): self
    {
        return new self("The MorphTo relation [{$relation}] of the resource [{$resource}] declares its targets with a resource class or a list of resource classes.");
    }

    public static function duplicateMorphType(string $resource, string $relation, string $type): self
    {
        return new self("The MorphTo relation [{$relation}] of the resource [{$resource}] declares two targets of the morph type [{$type}]. Declare each target model once.");
    }

    public static function unnamedMorphType(string $resource, string $relation, string $type): self
    {
        return new self("The MorphTo relation [{$relation}] of the resource [{$resource}] declares a target of the morph type [{$type}], which contains * or ends with a backslash. Map the model to another alias in the morph map.");
    }

    public static function morphTypeKeyField(string $resource, string $field, string $owner, string $relation): self
    {
        return new self("The resource [{$resource}] declares the field [{$field}], which is the type key of the rows that the MorphTo relation [{$relation}] of the resource [{$owner}] loads. Rename the field, or set rest.morph.type_key to another name.");
    }

    public static function notAResource(string $class, mixed $resolved): self
    {
        $type = get_debug_type($resolved);

        return new self("The container resolves the resource [{$class}] to [{$type}], which does not extend Lomkit\\Rest\\Http\\Resource.");
    }

    public static function softDeletesRoute(string $resource, string $model, string $endpoint): self
    {
        return new self("The routes of the resource [{$resource}] include the [{$endpoint}] endpoint, but its model [{$model}] does not use Illuminate\\Database\\Eloquent\\SoftDeletes. Use SoftDeletes in the model, or register the routes without withSoftDeletes().");
    }

    public static function unrestorableModel(string $resource, string $model): self
    {
        return new self("The resource [{$resource}] restores a row of the model [{$model}], which has no restore() method. Use Illuminate\\Database\\Eloquent\\SoftDeletes in the model.");
    }

    public static function unsearchableModel(string $model): self
    {
        return new self("The model [{$model}] is not indexed: it does not use Laravel\\Scout\\Searchable.");
    }

    public static function invalidScoutAttribute(string $model, string $method): self
    {
        return new self("The model [{$model}] returns an invalid value from {$method}().");
    }

    public static function integerScoutKey(string $model, mixed $key): self
    {
        $value = is_scalar($key) ? var_export($key, true) : get_debug_type($key);

        return new self("The scout key {$value} of the model [{$model}] is not an integer. Override getScoutKeyType() to return 'string' when the scout keys of the model are not integers.");
    }

    public static function invalidScoutKey(string $model, mixed $key): self
    {
        $type = get_debug_type($key);

        return new self("A scout key of the model [{$model}] is a value of type [{$type}]. Scout keys are strings or integers.");
    }
}
