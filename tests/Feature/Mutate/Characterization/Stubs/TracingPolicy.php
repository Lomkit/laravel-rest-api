<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class TracingPolicy
{
    /** @var list<string> */
    public static array $trace = [];

    public static function name(Model $model): string
    {
        $column = ['Book' => 'title', 'Loan' => 'borrower', 'Label' => 'text', 'Shelf' => 'name', 'Ticket' => 'title', 'Tag' => 'name', 'Team' => 'name'][class_basename($model)];

        return class_basename($model).'('.$model->getAttribute($column).')';
    }

    public function viewAny(?Authenticatable $user): bool
    {
        return self::record('viewAny');
    }

    public function view(?Authenticatable $user, Model $model): bool
    {
        return self::record('view', $model);
    }

    public function create(?Authenticatable $user): bool
    {
        return self::record('create');
    }

    public function update(?Authenticatable $user, Model $model): bool
    {
        return self::record('update', $model);
    }

    public function delete(?Authenticatable $user, Model $model): bool
    {
        return self::record('delete', $model);
    }

    public function attachTag(?Authenticatable $user, Model $model, Model $related): bool
    {
        return self::record('attachTag', $model, $related);
    }

    public function detachTag(?Authenticatable $user, Model $model, Model $related): bool
    {
        return self::record('detachTag', $model, $related);
    }

    private static function record(string $ability, Model ...$models): bool
    {
        self::$trace[] = trim('policy '.$ability.' '.implode(' ', array_map(self::name(...), $models)));

        return true;
    }
}
