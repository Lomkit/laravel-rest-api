<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\Details\FieldDetails;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\Constrained;
use Lomkit\Rest\Validation\Mutate\EntryRule;
use Lomkit\Rest\Validation\Mutate\RelatedEntryRule;

class Relation implements \JsonSerializable
{
    use Constrained;
    use Makeable;

    public string $relation;

    /** @var class-string<Resource> */
    protected string $type;

    /**
     * The displayable name of the relation.
     */
    public string $name;

    protected Resource $fromResource;

    private ?string $attachAbility = null;

    private ?string $detachAbility = null;

    public function __construct($relation, $type)
    {
        $this->relation = $relation;
        $this->type = $type;
    }

    /**
     * Get the name of the relation.
     *
     * @return string
     */
    public function name()
    {
        return $this->name ?? (new \ReflectionClass($this))->getShortName();
    }

    /** @param Builder<Model> $query */
    public function applyPerimeter(Builder $query): void
    {
        self::perimeter($this->resource(), $query);
    }

    /**
     * Check if the relation has multiple entries.
     *
     * @return bool
     */
    public function hasMultipleEntries()
    {
        return false;
    }

    /** @return list<string> */
    public function reservedKeys(): array
    {
        return [];
    }

    /**
     * Get the resource associated with this relation.
     *
     * @return \Lomkit\Rest\Http\Resource
     */
    public function resource()
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

    public function entryRule(): EntryRule
    {
        return new RelatedEntryRule($this);
    }

    /** @return array<string, mixed> */
    public function rules(Resource $resource, string $prefix)
    {
        return [];
    }

    #[\Override]
    public function jsonSerialize(): mixed
    {
        return $this->toDetails();
    }

    /** @return array<string, mixed> */
    public function toDetails(): array
    {
        $details = [
            'resource' => $this->type,
            'relation' => $this->relation,
            'constraints' => [
                'required_on_creation' => $this->isRequiredOnCreation(),
                'prohibited_on_creation' => $this->isProhibitedOnCreation(),
                'required_on_update' => $this->isRequiredOnUpdate(),
                'prohibited_on_update' => $this->isProhibitedOnUpdate(),
            ],
            'name' => $this->name(),
        ];

        if (! $this instanceof PivotRelation) {
            return $details;
        }

        return [
            ...$details,
            'pivot_fields' => FieldDetails::forPivotFields($this->getPivotFields(), $this->modelRelation() instanceof EloquentBelongsToMany),
        ];
    }

    protected function constraintResource(): ?Resource
    {
        return $this->resource();
    }

    /** @param Builder<Model> $query */
    protected static function perimeter(Resource $resource, Builder $query): void
    {
        $resource->context()->authorizer()->ensurePlanned(Check::onClass($resource, 'viewAny'));

        $resource->perimeterQuery($resource->context()->request, $query);
    }

    protected function modelRelation(): mixed
    {
        return $this->fromResource::newModel()->{$this->relation}();
    }
}
