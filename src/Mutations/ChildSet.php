<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Validation\ValidationException;
use LogicException;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Support\Translation;

final class ChildSet
{
    /** @var array<int, list<list<Model>>> */
    private array $plans = [];

    /** @var array<string, array<string, true>> */
    private array $removed = [];

    /**
     * @param  list<Model>  $listed
     * @return list<Model>
     */
    public function plan(Relation $relation, Model $parent, MutationNode $node, array $listed): array
    {
        $removed = $parent->exists ? $this->unlisted($relation, $parent, $node, $listed) : [];
        $this->plans[spl_object_id($node)][] = $removed;

        return $removed;
    }

    /** @return list<Model> */
    public function written(MutationNode $node): array
    {
        $queue = $this->plans[spl_object_id($node)] ?? [];
        $removed = array_shift($queue) ?? throw new LogicException("The children of the entry [{$node->path}] are written before they are checked.");
        $this->plans[spl_object_id($node)] = $queue;

        return $removed;
    }

    /**
     * @param  list<Model>  $listed
     * @return list<Model>
     */
    private function unlisted(Relation $relation, Model $parent, MutationNode $node, array $listed): array
    {
        $max = $relation
            ->resource()
            ->context()
            ->limits()->totals->entries;
        $rows = $this->read($relation, $parent, $listed, $max + 1);

        if (count($rows) > $max) {
            throw self::refusal($node, $max);
        }

        $slot = spl_object_id($parent).'.'.$relation->relation;
        $removed = [];

        foreach ($rows as $row) {
            $identity = LinkColumn::identity($row);

            if (! isset($this->removed[$slot][$identity])) {
                $this->removed[$slot][$identity] = true;
                $removed[] = $row;
            }
        }

        return $removed;
    }

    /**
     * @param  list<Model>  $listed
     * @return list<Model>
     */
    private function read(Relation $relation, Model $parent, array $listed, int $limit): array
    {
        $parent
            ->newQueryWithoutScopes()
            ->whereKey($parent->getKey())
            ->lockForUpdate()
            ->toBase()
            ->get([$parent->getKeyName()]);

        $children = self::children($relation, $parent);
        $key = $children->getRelated()->getQualifiedKeyName();
        (new PerimeterClause($relation->resource()))->apply($children);

        if ($listed !== []) {
            $children->whereNotIn($key, array_map(fn (Model $row): mixed => $row->getKey(), $listed));
        }

        return array_values($children
            ->orderBy($key)
            ->limit($limit)
            ->lockForUpdate()
            ->get()
            ->all());
    }

    /** @return HasOneOrMany<Model, Model, mixed> */
    private static function children(Relation $relation, Model $parent): HasOneOrMany
    {
        $children = $parent->{$relation->relation}();

        if (! $children instanceof HasOneOrMany) {
            throw new LogicException("The relation [{$relation->relation}] is not a HasMany or MorphMany relation.");
        }

        return $children;
    }

    private static function refusal(MutationNode $node, int $max): ValidationException
    {
        $key = $node->path.'.key';

        return ValidationException::withMessages([$key => [Translation::line('rest::validation.sync_removed_total', ['attribute' => $key, 'max' => (string) $max])]]);
    }
}
