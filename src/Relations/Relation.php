<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\Constrained;
use Lomkit\Rest\Relations\Traits\Mutates;

class Relation implements \JsonSerializable
{
    use Makeable;
    use Mutates;
    use Constrained;
    public string $relation;
    protected string $type;

    /**
     * The displayable name of the relation.
     */
    public string $name;

    protected Resource $fromResource;

    /**
     * Whether a negated filter on this relation means no related entry matches.
     */
    public bool $negationsAsAbsence = false;

    public function __construct($relation, $type)
    {
        $this->relation = $relation;
        $this->type = $type;
    }

    /**
     * Read negated filters on this relation as "no related entry matches"
     * instead of "at least one related entry does not match".
     *
     * The relation holding the filtered field decides, the last one of a dotted path.
     * "is null" and "is not null" are not negations of each other and stay as they are.
     *
     * @param bool $negationsAsAbsence
     *
     * @return $this
     */
    public function negationsAsAbsence(bool $negationsAsAbsence = true)
    {
        $this->negationsAsAbsence = $negationsAsAbsence;

        return $this;
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
     * @param Builder      $query
     * @param mixed        $relation
     * @param mixed        $operator
     * @param mixed        $value
     * @param string       $boolean
     * @param Closure|null $callback
     *
     * @return Builder
     */
    public function filter(Builder $query, $relation, $operator, $value, $boolean = 'and', ?Closure $callback = null)
    {
        // Here the negation moves from the predicate to the existence check: no related entry may match
        $absence = $this->negationsAsAbsence && in_array($operator, ['!=', 'not in', 'not like', 'not ilike', 'not between'], true);

        if ($absence) {
            $operator = $operator === '!=' ? '=' : Str::after($operator, 'not ');
        }

        return $query->has(Str::beforeLast(relation_without_pivot($relation), '.'), $absence ? '<' : '>=', 1, $boolean, function (Builder $query) use ($value, $operator, $relation, $callback) {
            $field = (Str::contains($relation, '.pivot.') ?
                    $this->fromResource::newModel()->{Str::of($relation)->before('.pivot.')->afterLast('.')->toString()}()->getTable() :
                    $query->getModel()->getTable()).'.'.Str::afterLast($relation, '.');

            if (in_array($operator, ['in', 'not in'])) {
                $query->whereIn($field, $value, 'and', $operator === 'not in');
            } elseif (in_array($operator, ['between', 'not between'])) {
                $query->whereBetween($field, $value, 'and', $operator === 'not between');
            } elseif (in_array($operator, ['is null', 'is not null'])) {
                $query->whereNull($field, 'and', $operator === 'is not null');
            } elseif (in_array($operator, ['ilike', 'not ilike'])) {
                // Compiles to the case insensitive operator of the driver
                $query->whereLike($field, $value, false, 'and', $operator === 'not ilike');
            } else {
                $query->where($field, $operator, $value);
            }

            $callback($query);
        });
    }

    /**
     * Apply a search query to the relation's builder.
     *
     * @param Builder $query
     */
    public function applySearchQuery(Builder $query)
    {
        $resource = $this->resource();

        $resource->searchQuery(app()->make(RestRequest::class), $query);
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
        $resource = $this->type;

        // If the resource isn't registered, do it
        if (!app()->has($resource)) {
            app()->singleton($resource);
        }

        return app()->make($resource);
    }

    /**
     * Set the "fromResource" property of the relation.
     *
     * @param resource $fromResource
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
     * @param resource $resource
     * @param string   $prefix
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
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        $request = app(RestRequest::class);

        return [
            'resource'    => $this->type,
            'relation'    => $this->relation,
            'constraints' => [
                'required_on_creation'   => $this->isRequiredOnCreation($request),
                'prohibited_on_creation' => $this->isProhibitedOnCreation($request),
                'required_on_update'     => $this->isRequiredOnUpdate($request),
                'prohibited_on_update'   => $this->isProhibitedOnUpdate($request),
            ],
            'name' => $this->name(),
        ];
    }
}
