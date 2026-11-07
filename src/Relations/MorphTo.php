<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;
use LogicException;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\Mutate\EntryRule;
use Lomkit\Rest\Validation\Mutate\MorphToEntryRule;

class MorphTo extends BelongsTo implements PolymorphicRelation
{
    /** @var array<array-key, mixed> */
    private readonly array $resources;

    private ?MorphTargets $targets = null;

    /** @param class-string<Resource>|list<class-string<Resource>> $types */
    public function __construct(string $relation, string|array $types)
    {
        $resources = is_array($types) ? $types : [$types];
        $first = reset($resources);
        $type = is_string($first) && is_subclass_of($first, Resource::class) ? $first : Resource::class;

        parent::__construct($relation, $type);

        $this->resources = $resources;
    }

    /** @param class-string<Resource>|list<class-string<Resource>> $types */
    #[\Override]
    public static function make(string $relation, string|array $types): static
    {
        return new static($relation, $types);
    }

    #[\Override]
    public function fromResource(Resource $fromResource): static
    {
        parent::fromResource($fromResource);

        $this->targets = MorphTargets::of($this->resources, $fromResource::class, $this->relation);

        return $this;
    }

    #[\Override]
    public function targets(): MorphTargets
    {
        return $this->targets ?? throw new LogicException("The MorphTo relation [{$this->relation}] is not bound to a resource.");
    }

    #[\Override]
    public function resource(): Resource
    {
        $target = $this->targets()->single() ?? throw new LogicException("The MorphTo relation [{$this->relation}] has several targets: resolve one with targets().");

        return $this->resourceOf($target);
    }

    #[\Override]
    public function resourceOf(MorphTarget $target): Resource
    {
        $context = $this->fromResource->context();
        $resource = $context->resource($target->resource);
        $key = $context->config()->morphTypeKey;

        $fields = $resource->definition()->fields();

        if ($fields->has($key)) {
            throw InvalidResourceException::morphTypeKeyField($resource::class, $key, $this->fromResource::class, $this->relation);
        }

        return $resource;
    }

    #[\Override]
    public function resourceFor(Model $model): ?Resource
    {
        $target = $this->targets()->forModel($model);

        return $target === null ? null : $this->resourceOf($target);
    }

    /** @param Builder<Model> $query */
    #[\Override]
    public function applyPerimeter(Builder $query): void
    {
        $resource = $this->resourceFor($query->getModel()) ?? throw new LogicException($query->getModel()::class." is not a target of the MorphTo relation [{$this->relation}].");

        self::perimeter($resource, $query);
    }

    #[\Override]
    public function entryRule(): EntryRule
    {
        return new MorphToEntryRule($this);
    }

    #[\Override]
    public function columns(): MorphColumns
    {
        $relation = $this->modelRelation();

        if (! $relation instanceof EloquentMorphTo) {
            throw InvalidResourceException::notAMorphTo($this->fromResource::class, $this->relation);
        }

        if ($relation->getRelationName() !== $this->relation) {
            throw InvalidResourceException::morphRelationName($this->fromResource::class, $this->relation, $relation->getRelationName());
        }

        return MorphColumns::of($relation);
    }

    /** @return list<string> */
    #[\Override]
    public function reservedKeys(): array
    {
        return [$this->fromResource->context()->config()->morphTypeKey];
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toDetails(): array
    {
        $details = [
            ...parent::toDetails(),
            'types' => (object) $this->targets()->byType(),
            'morph' => $this->columns(),
        ];

        if ($this->targets()->isPolymorphic()) {
            unset($details['resource']);
        }

        return $details;
    }

    #[\Override]
    protected function constraintResource(): ?Resource
    {
        return $this->targets()->isPolymorphic() ? null : $this->resource();
    }
}
