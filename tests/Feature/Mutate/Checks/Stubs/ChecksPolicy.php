<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Changes;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;

class ChecksPolicy
{
    /** @var list<string> */
    public static array $refused = [];

    public static function describe(mixed $argument): string
    {
        return match (true) {
            $argument instanceof Model && ! $argument->exists => class_basename($argument).'* '.json_encode($argument->getAttributes()),
            $argument instanceof Model => TracingPolicy::name($argument),
            $argument instanceof Changes => (string) json_encode($argument->all()),
            is_string($argument) => class_basename($argument),
            default => get_debug_type($argument),
        };
    }

    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function view(?Authenticatable $user, Model $model): Response
    {
        return self::answer('view', func_get_args());
    }

    public function create(?Authenticatable $user): Response
    {
        return self::answer('create', func_get_args());
    }

    public function update(?Authenticatable $user, Model $model): Response
    {
        return self::answer('update', func_get_args());
    }

    public function attachLoan(?Authenticatable $user, Model $model, Model $related): Response
    {
        return self::answer('attachLoan', func_get_args());
    }

    public function attachShelf(?Authenticatable $user, Model $model, Model $related): Response
    {
        return self::answer('attachShelf', func_get_args());
    }

    public function detachShelf(?Authenticatable $user, Model $model, Model $related): Response
    {
        return self::answer('detachShelf', func_get_args());
    }

    /** @param list<mixed> $arguments */
    private static function answer(string $ability, array $arguments): Response
    {
        $call = implode(' ', [$ability, ...array_map(self::describe(...), array_slice($arguments, 1))]);
        TracingPolicy::$trace[] = 'policy '.$call;

        return in_array($call, self::$refused, true) ? Response::deny('Refused: '.$call.'.') : Response::allow();
    }
}
