<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\Relation;

final class OperationEntry implements JsonSerializable
{
    /** @var list<mixed> */
    private array $written = [];

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

    public static function of(MutationNode $node, bool $typed = false, bool $listed = false): self
    {
        return new self($node->operation, $node->key, $typed, $listed, array_fill_keys(array_keys($node->relations ?? []), []));
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

    public function child(MutationNode $node): self
    {
        if ($this->relation === null) {
            return self::of($node);
        }

        $name = $this->relation->relation;
        $index = $this->cursors[$name] ?? 0;
        $this->cursors[$name] = $index + 1;

        if (isset($this->relations[$name][$index])) {
            return $this->relations[$name][$index];
        }

        $entry = self::of($node, $this->relation instanceof PolymorphicRelation, $this->listed || is_array($this->key));
        $this->relations[$name][] = $entry;

        return $entry;
    }

    public function wrote(Model $model): void
    {
        $this->written[] = $model->getKey();

        if ($this->typed) {
            $this->type = $model->getMorphClass();
        }
    }

    /** @return list<mixed> */
    public function writtenKeys(): array
    {
        return $this->written;
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
            return $this->written;
        }

        return match (count($this->written)) {
            0 => null,
            1 => $this->written[0],
            default => $this->written,
        };
    }
}
