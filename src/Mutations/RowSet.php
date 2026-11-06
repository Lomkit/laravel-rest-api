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
use Lomkit\Rest\Validation\KeysGrammar;

final class RowSet
{
    private const int CHUNK = 1000;

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

    /**
     * @param  list<mixed>  $keys
     * @return list<Model>
     */
    public function linked(Resource $resource, array $keys): array
    {
        $this->index();

        $class = $resource::newModel()::class;
        $rows = [];

        foreach (array_chunk($keys, self::CHUNK) as $chunk) {
            $loaded = $this->load($resource, array_values(array_filter($chunk, fn (mixed $key): bool => self::pick($this->rows, $class, $key) === null)));

            foreach ($chunk as $key) {
                $held = self::pick($this->rows, $class, $key);
                $row = $held === null ? self::pick($loaded, $class, $key) : $this->hand($held);

                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @param  list<mixed>  $keys
     * @return array<string, Model>
     */
    private function load(Resource $resource, array $keys): array
    {
        $keys = array_values(array_filter($keys, fn (mixed $key): bool => is_int($key) || is_string($key)));

        if ($keys === []) {
            return [];
        }

        $model = $resource::newModel();
        $loaded = [];
        $rows = KeysGrammar::idOf($resource)
            ->whereKeys($model->newQuery(), $keys)
            ->get();

        foreach ($rows as $row) {
            $identity = self::identity($model::class, self::keyOf($row));

            if ($identity !== null) {
                $loaded[$identity] = $row;
            }
        }

        return $loaded;
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

    /** @param array<string, Model> $rows */
    private static function pick(array $rows, string $class, mixed $key): ?Model
    {
        $identity = self::identity($class, $key);

        return $identity === null ? null : $rows[$identity] ?? null;
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
