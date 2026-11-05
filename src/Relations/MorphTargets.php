<?php

namespace Lomkit\Rest\Relations;

use ArrayIterator;
use Illuminate\Database\Eloquent\Model;
use IteratorAggregate;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Resource;

/** @implements IteratorAggregate<string, MorphTarget> */
final readonly class MorphTargets implements IteratorAggregate
{
    /** @param non-empty-array<string, MorphTarget> $targets */
    private function __construct(private array $targets) {}

    /** @param array<array-key, mixed> $resources */
    public static function of(array $resources, string $owner, string $relation): self
    {
        if ($resources === [] || ! array_is_list($resources)) {
            throw InvalidResourceException::invalidMorphTargets($owner, $relation);
        }

        $targets = [];

        foreach ($resources as $resource) {
            if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
                throw InvalidResourceException::invalidMorphTargets($owner, $relation);
            }

            $type = $resource::newModel()->getMorphClass();
            $targets[$type] = new MorphTarget($type, $resource);
        }

        return new self($targets);
    }

    /** @return ArrayIterator<string, MorphTarget> */
    #[\Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->targets);
    }

    /** @return non-empty-list<class-string<Resource>> */
    public function resources(): array
    {
        return array_values(array_map(static fn (MorphTarget $target): string => $target->resource, $this->targets));
    }

    /** @return list<class-string<Model>> */
    public function modelClasses(): array
    {
        return array_map(static fn (string $resource): string => $resource::modelClass(), $this->resources());
    }

    /** @return non-empty-list<string> */
    public function types(): array
    {
        return array_keys($this->targets);
    }

    /** @return array<string, class-string<Resource>> */
    public function byType(): array
    {
        return array_map(static fn (MorphTarget $target): string => $target->resource, $this->targets);
    }

    public function forType(mixed $type): ?MorphTarget
    {
        if ($type === null) {
            return $this->single();
        }

        return is_string($type) ? $this->targets[$type] ?? null : null;
    }

    public function single(): ?MorphTarget
    {
        return $this->isPolymorphic() ? null : $this->targets[array_key_first($this->targets)];
    }

    public function forModel(Model $model): ?MorphTarget
    {
        return $this->targets[$model->getMorphClass()] ?? null;
    }

    public function isPolymorphic(): bool
    {
        return count($this->targets) > 1;
    }
}
