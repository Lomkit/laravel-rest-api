<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\Constrained;
use Lomkit\Rest\Relations\Traits\Mutates;

class Relation implements \JsonSerializable
{
    use Constrained;
    use Makeable;
    use Mutates;

    public string $relation;

    /** @var class-string<Resource> */
    protected string $type;

    /**
     * The displayable name of the relation.
     */
    public string $name;

    protected Resource $fromResource;

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

    /**
     * Apply a search query to the relation's builder.
     */
    public function applySearchQuery(Builder $query)
    {
        $resource = $this->resource();

        $resource->context()->authorizer()->ensurePlanned(Check::onClass($resource, 'viewAny'));

        $resource->searchQuery($resource->context()->request, $query);
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

    /**
     * Get the resource associated with this relation.
     *
     * @return \Lomkit\Rest\Http\Resource
     */
    public function resource()
    {
        return $this->fromResource->context()->resource($this->type);
    }

    /**
     * Set the "fromResource" property of the relation.
     *
     *
     * @return $this
     */
    public function fromResource(Resource $fromResource)
    {
        return tap($this, function () use ($fromResource) {
            $this->fromResource = $fromResource;
        });
    }

    /** @return array<string, mixed> */
    public function rules(Resource $resource, string $prefix)
    {
        return $this instanceof PivotRelation ? $this->pivotRules($prefix) : [];
    }

    /**
     * Serialize the object to JSON.
     */
    #[\Override]
    public function jsonSerialize(): mixed
    {
        $request = $this->fromResource->context()->request;

        return [
            'resource' => $this->type,
            'relation' => $this->relation,
            'constraints' => [
                'required_on_creation' => $this->isRequiredOnCreation($request),
                'prohibited_on_creation' => $this->isProhibitedOnCreation($request),
                'required_on_update' => $this->isRequiredOnUpdate($request),
                'prohibited_on_update' => $this->isProhibitedOnUpdate($request),
            ],
            'name' => $this->name(),
        ];
    }
}
