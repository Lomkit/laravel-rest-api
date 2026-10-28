<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Relations\HasPivotFields;
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
     * Filter the query based on the relation.
     *
     * @param  mixed  $relation
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return Builder
     */
    public function filter(Builder $query, $relation, $operator, $value, $boolean = 'and', ?Closure $callback = null)
    {
        return $query->has(Str::beforeLast(relation_without_pivot($relation), '.'), '>=', 1, $boolean, function (Builder $query) use ($value, $operator, $relation, $callback) {
            $field = (Str::contains($relation, '.pivot.') ?
                    $this->fromResource::newModel()->{Str::of($relation)->before('.pivot.')->afterLast('.')->toString()}()->getTable() :
                    $query->getModel()->getTable()).'.'.Str::afterLast($relation, '.');

            if (in_array($operator, ['in', 'not in'])) {
                $query->whereIn($field, $value, 'and', $operator === 'not in');
            } else {
                $query->where($field, $operator, $value);
            }

            $callback($query);
        });
    }

    /**
     * Apply a search query to the relation's builder.
     */
    public function applySearchQuery(Builder $query)
    {
        $resource = $this->resource();

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

    /**
     * Get the validation rules for this relation.
     *
     *
     * @return array
     */
    public function rules(Resource $resource, string $prefix)
    {
        $rules = [];

        if (in_array(HasPivotFields::class, class_uses_recursive($this), true)) {
            $pivotPrefix = $prefix;
            if ($this->hasMultipleEntries()) {
                $pivotPrefix .= '.*';
            }
            $pivotPrefix .= '.pivot.';

            foreach ($this->getPivotRules() as $pivotKey => $pivotRule) {
                $rules[$pivotPrefix.$pivotKey] = $pivotRule;
            }
        }

        return $rules;
    }

    /**
     * Serialize the object to JSON.
     */
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
