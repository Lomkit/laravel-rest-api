<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final readonly class LinkColumn
{
    private const int CHUNK = 1000;

    public string $id;

    private function __construct(
        private Model $child,
        private string $foreignKey,
        private ?string $morphType,
    ) {
        $this->id = self::table($child).'.'.$foreignKey.'.'.($morphType ?? '');
    }

    /** @param HasOneOrMany<Model, Model, mixed> $relation */
    public static function ofChildren(HasOneOrMany $relation): self
    {
        return new self($relation->getRelated(), $relation->getForeignKeyName(), $relation instanceof MorphOneOrMany ? $relation->getMorphType() : null);
    }

    /** @param BelongsTo<Model, Model> $relation */
    public static function ofOwner(BelongsTo $relation): self
    {
        return new self($relation->getChild(), $relation->getForeignKeyName(), $relation instanceof MorphTo ? $relation->getMorphType() : null);
    }

    public static function identity(Model $row): string
    {
        return self::table($row).'#'.self::text($row->getRawOriginal($row->getKeyName()));
    }

    public static function owner(?string $type, mixed $key): ?string
    {
        return $key === null ? null : ($type ?? '')."\n".self::text($key);
    }

    public function nullable(NullableColumns $columns): bool
    {
        return $columns->allows($this->child, $this->foreignKey) && ($this->morphType === null || $columns->allows($this->child, $this->morphType));
    }

    public function of(Model $row): ?string
    {
        return self::owner($this->morphType === null ? null : self::text($row->getRawOriginal($this->morphType)), $row->getRawOriginal($this->foreignKey));
    }

    /**
     * @param  array<string, array{?string, mixed}>  $owners
     * @return array<string, string>
     */
    public function children(array $owners): array
    {
        $children = [];

        foreach (self::byType($owners) as $type => $keys) {
            foreach (array_chunk($keys, self::CHUNK) as $chunk) {
                foreach ($this->read($type, $chunk) as $row) {
                    $children[self::table($this->child).'#'.self::text($row[0])] = (string) self::owner($type === '' ? null : $type, $row[1]);
                }
            }
        }

        return $children;
    }

    /**
     * @param  list<mixed>  $keys
     * @return list<array{mixed, mixed}>
     */
    private function read(string $type, array $keys): array
    {
        $key = $this->child->qualifyColumn($this->child->getKeyName());
        $foreign = $this->child->qualifyColumn($this->foreignKey);
        $query = $this->child
            ->newQuery()
            ->whereIn($foreign, $keys);

        if ($this->morphType !== null) {
            $query->where($this->child->qualifyColumn($this->morphType), $type);
        }

        $rows = $query
            ->toBase()
            ->get([$key, $foreign])
            ->all();

        return array_values(array_map(fn (object $row): array => [$row->{$this->child->getKeyName()}, $row->{$this->foreignKey}], $rows));
    }

    /**
     * @param  array<string, array{?string, mixed}>  $owners
     * @return array<string, list<mixed>>
     */
    private static function byType(array $owners): array
    {
        $types = [];

        foreach ($owners as [$type, $key]) {
            $types[$type ?? ''][] = $key;
        }

        return $types;
    }

    private static function table(Model $model): string
    {
        return $model->getConnection()->getName().'.'.$model->getTable();
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
