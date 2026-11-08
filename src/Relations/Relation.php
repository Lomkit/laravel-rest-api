<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\Details\FieldDetails;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Details\DetailsSelection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\Constrained;
use Lomkit\Rest\Validation\Mutate\EntryRule;
use Lomkit\Rest\Validation\Mutate\RelatedEntryRule;

/** @phpstan-consistent-constructor */
class Relation
{
    use Constrained;

    public string $relation;

    /** @var class-string<Resource> */
    protected string $type;

    public string $name;

    protected Resource $fromResource;

    private ?string $attachAbility = null;

    private ?string $detachAbility = null;

    /** @param class-string<Resource> $type */
    public function __construct(string $relation, string $type)
    {
        $this->relation = $relation;
        $this->type = $type;
    }

    /** @param class-string<Resource> $type */
    public static function make(string $relation, string $type): static
    {
        return new static($relation, $type);
    }

    public function name(): string
    {
        return $this->name ?? (new \ReflectionClass($this))->getShortName();
    }

    /** @param Builder<Model> $query */
    public function applyPerimeter(Builder $query): void
    {
        self::perimeter($this->resource(), $query);
    }

    public function hasMultipleEntries(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function reservedKeys(): array
    {
        return [];
    }

    public function resource(): Resource
    {
        return $this->fromResource->context()->resource($this->type);
    }

    public function fromResource(Resource $fromResource): static
    {
        $this->fromResource = $fromResource;

        return $this;
    }

    public function parentResource(): Resource
    {
        return $this->fromResource;
    }

    public function abilities(?string $attach = null, ?string $detach = null): static
    {
        $this->attachAbility = $attach;
        $this->detachAbility = $detach;

        return $this;
    }

    public function linkAbility(bool $detach): ?string
    {
        return $detach ? $this->detachAbility : $this->attachAbility;
    }

    /** @return list<MutationOperation> */
    public function mutationOperations(): array
    {
        return [MutationOperation::Create, MutationOperation::Update, MutationOperation::Attach, MutationOperation::Detach];
    }

    public function isDeletable(): bool
    {
        return false;
    }

    /** @return list<MutationOperation> */
    final public function acceptedOperations(): array
    {
        $operations = array_values(array_filter($this->mutationOperations(), fn (MutationOperation $operation): bool => $operation !== MutationOperation::Delete));

        return $this->isDeletable() ? [...$operations, MutationOperation::Delete] : $operations;
    }

    public function entryRule(): EntryRule
    {
        return new RelatedEntryRule($this);
    }

    /** @return array<string, mixed> */
    public function rules(Resource $resource, string $prefix): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function toDetails(DetailsSelection $selection): array
    {
        return [
            'resource' => $this->type,
            'relation' => $this->relation,
            ...$selection->part('relations.constraints', fn (): array => ['constraints' => [
                'required_on_creation' => $this->isRequiredOnCreation(),
                'prohibited_on_creation' => $this->isProhibitedOnCreation(),
                'required_on_update' => $this->isRequiredOnUpdate(),
                'prohibited_on_update' => $this->isProhibitedOnUpdate(),
            ]]),
            ...$selection->part('relations.operations', fn (): array => ['operations' => array_column($this->acceptedOperations(), 'value')]),
            'name' => $this->name(),
            ...$selection->part('relations.pivot_fields', $this->pivotFieldDetails(...)),
        ];
    }

    /** @return array{pivot_fields?: list<FieldDetails>} */
    private function pivotFieldDetails(): array
    {
        if (! $this instanceof PivotRelation) {
            return [];
        }

        return ['pivot_fields' => FieldDetails::forPivotFields($this->getPivotFields(), $this->modelRelation() instanceof EloquentBelongsToMany)];
    }

    protected function constraintResource(): ?Resource
    {
        return $this->resource();
    }

    /** @param Builder<Model> $query */
    protected static function perimeter(Resource $resource, Builder $query): void
    {
        $resource->context()->authorizer()->ensurePlanned(Check::onClass($resource, 'viewAny'));

        $resource->perimeterQuery($query);
    }

    protected function modelRelation(): mixed
    {
        return $this->fromResource::newModel()->{$this->relation}();
    }
}
