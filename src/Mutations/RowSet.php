<?php

namespace Lomkit\Rest\Mutations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\KeyLookup;
use Lomkit\Rest\Validation\KeyRead;

final class RowSet
{
    /** @var array<class-string<Resource>, KeyLookup> */
    private array $lookups = [];

    /** @var array<class-string<Resource>, array<array-key, Model>> */
    private array $named = [];

    /** @var array<string, Model> */
    private array $rows = [];

    /** @var array<string, Model> */
    private array $handed = [];

    /** @param Closure(): Builder<Model> $query */
    public function lookup(Resource $resource, Closure $query, ID $id): KeyLookup
    {
        return $this->lookups[$resource::class] ??= new KeyLookup($query, $id, KeyRead::Rows);
    }

    public function find(Resource $resource, int|string|null $key): Model
    {
        $this->index();

        $row = $key === null ? null : $this->named[$resource::class][$key] ?? null;

        if ($row === null) {
            throw (new ModelNotFoundException)->setModel($resource::modelClass(), $key === null ? [] : [$key]);
        }

        return $this->hand($row)->syncChanges();
    }

    public function peek(Resource $resource, int|string $key): ?Model
    {
        $this->index();

        return $this->named[$resource::class][$key] ?? null;
    }

    /** @return list<Model> */
    public function loaded(): array
    {
        $this->index();

        return array_merge(...array_map(array_values(...), array_values($this->named)));
    }

    private function index(): void
    {
        foreach ($this->lookups as $class => $lookup) {
            $model = $class::newModel()::class;

            foreach ($lookup->found() as $key => $row) {
                $identity = self::identity($model, self::keyOf($row));
                $this->named[$class][$key] = $identity === null ? $row : $this->rows[$identity] ??= $row;
            }
        }

        $this->lookups = [];
    }

    private function hand(Model $row): Model
    {
        $identity = self::identity($row->getConnection()->getName().'.'.$row->getTable(), self::keyOf($row));

        if ($identity === null) {
            return $row;
        }

        $held = $this->handed[$identity] ?? $row;
        $this->handed[$identity] = $row;

        if ($held === $row) {
            return $row;
        }

        $dirty = $row->getDirty();

        return $row
            ->setRawAttributes($held->getRawOriginal(), true)
            ->setRawAttributes([...$held->getRawOriginal(), ...$dirty]);
    }

    private static function keyOf(Model $row): mixed
    {
        return $row->getRawOriginal($row->getKeyName());
    }

    private static function identity(string $class, mixed $key): ?string
    {
        return is_int($key) || is_string($key) ? $class.'#'.$key : null;
    }
}
