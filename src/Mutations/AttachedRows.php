<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Relations\Relation;

final class AttachedRows
{
    private const int CHUNK = 1000;

    /** @var array<array-key, Model> */
    private array $rows = [];

    /** @var array<array-key, true> */
    private array $read = [];

    private bool $complete = false;

    public function __construct(
        private readonly Relation $relation,
        private readonly Model $parent,
    ) {}

    /**
     * @param  array<array-key, Model>  $named
     * @return array<array-key, Model>
     */
    public function among(array $named): array
    {
        if ($this->complete || ! $this->parent->exists) {
            return $this->rows;
        }

        $keys = [];

        foreach ($named as $name => $row) {
            if ($row->exists && ! isset($this->read[$name])) {
                $keys[] = $row->getKey();
                $this->read[$name] = true;
            }
        }

        foreach (array_chunk($keys, self::CHUNK) as $chunk) {
            $pivots = $this->pivots();
            $this->keep($pivots->whereIn($pivots->getRelated()->getQualifiedKeyName(), $chunk)->get()->all());
        }

        return $this->rows;
    }

    /** @return array<array-key, Model> */
    public function all(int $limit): array
    {
        if ($this->complete || ! $this->parent->exists) {
            return $this->rows;
        }

        $rows = $this->pivots()
            ->limit($limit)
            ->get()
            ->all();
        $this->rows = [];
        $this->keep($rows);
        $this->complete = count($rows) < $limit;

        return $this->rows;
    }

    public static function key(Model $row): string
    {
        if (! $row->exists) {
            return 'new#'.spl_object_id($row);
        }

        $key = $row->getKey();

        return is_int($key) || is_string($key) ? (string) $key : throw new LogicException($row::class.' has a key that cannot name a pivot row.');
    }

    /** @param array<int, Model> $rows */
    private function keep(array $rows): void
    {
        foreach ($rows as $row) {
            $this->rows[self::key($row)] = $row;
        }
    }

    /** @return BelongsToMany<Model, Model> */
    private function pivots(): BelongsToMany
    {
        $pivots = $this->parent->{$this->relation->relation}();

        if (! $pivots instanceof BelongsToMany) {
            throw new LogicException("The relation [{$this->relation->relation}] is not a BelongsToMany, MorphToMany or MorphedByMany relation.");
        }

        (new PerimeterClause($this->relation->resource()))->apply($pivots);

        return $pivots;
    }
}
