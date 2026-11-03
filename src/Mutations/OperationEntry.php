<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Relations\Relation;

final class OperationEntry implements JsonSerializable
{
    /** @var list<mixed> */
    private array $created = [];

    private ?string $type = null;

    private ?Relation $relation = null;

    /** @var array<string, int> */
    private array $cursors = [];

    /** @var array<string, bool> */
    private array $lists = [];

    /**
     * @param  int|string|list<int|string>|null  $key
     * @param  array<string, list<OperationEntry>>  $relations
     */
    private function __construct(
        private readonly MutationOperation $operation,
        private readonly int|string|array|null $key,
        private readonly bool $typed,
        private readonly bool $listed,
        private array $relations,
    ) {}

    /** @param array<string, mixed> $mutation */
    public static function of(array $mutation, bool $typed = false, bool $listed = false): self
    {
        $operation = MutationOperation::from(is_string($mutation['operation'] ?? null) ? $mutation['operation'] : '');
        $relations = [];

        foreach (array_keys(is_array($mutation['relations'] ?? null) ? $mutation['relations'] : []) as $name) {
            $relations[(string) $name] = [];
        }

        return new self($operation, self::key($mutation['key'] ?? null), $typed, $listed, $relations);
    }

    public function within(Relation $relation): void
    {
        $this->relation = $relation;
        $this->lists[$relation->relation] = $relation->hasMultipleEntries();
    }

    public function rewind(): void
    {
        $this->cursors = [];
    }

    /** @param array<string, mixed> $mutation */
    public function child(array $mutation): self
    {
        if ($this->relation === null) {
            return self::of($mutation);
        }

        $name = $this->relation->relation;
        $index = $this->cursors[$name] ?? 0;
        $this->cursors[$name] = $index + 1;

        if (isset($this->relations[$name][$index])) {
            return $this->relations[$name][$index];
        }

        $entry = self::of($mutation, $this->relation instanceof MorphTo, $this->listed || is_array($this->key));
        $this->relations[$name][] = $entry;

        return $entry;
    }

    public function wrote(Model $model): void
    {
        if ($this->operation === MutationOperation::Create) {
            $this->created[] = $model->getKey();
        }

        if ($this->typed) {
            $this->type = $model->getMorphClass();
        }
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function jsonSerialize(): array
    {
        $entry = ['operation' => $this->operation->value];

        if ($this->type !== null) {
            $entry['type'] = $this->type;
        }

        $entry['key'] = $this->operation === MutationOperation::Create ? $this->createdKey() : $this->key;

        if ($this->relations === []) {
            return $entry;
        }

        $entry['relations'] = [];

        foreach ($this->relations as $name => $entries) {
            $entry['relations'][$name] = $this->lists[$name] ?? true ? $entries : $entries[0] ?? null;
        }

        return $entry;
    }

    private function createdKey(): mixed
    {
        if ($this->listed) {
            return $this->created;
        }

        return match (count($this->created)) {
            0 => null,
            1 => $this->created[0],
            default => $this->created,
        };
    }

    /** @return int|string|list<int|string>|null */
    private static function key(mixed $key): int|string|array|null
    {
        if (! is_array($key)) {
            return is_int($key) || is_string($key) ? $key : null;
        }

        return array_values(array_filter($key, fn (mixed $item): bool => is_int($item) || is_string($item)));
    }
}
