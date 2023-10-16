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
use Lomkit\Rest\Rules\RequiredRelationOnCreation;

class Relation implements \JsonSerializable
{
    use Makeable;
    use Mutates;
    use Constrained;
    public string $relation;
    protected array $types;

    /**
     * The displayable name of the relation.
     *
     * @var string
     */
    public $name;

    protected Resource $fromResource;

    public function __construct($relation, $type)
    {
        $this->relation = $relation;
        $this->types = [$type];
    }

    /**
     * Get the name of the relation.
     *
     * @return string
     */
    public function name()
    {
        return $this->name ?: (new \ReflectionClass($this))->getShortName();
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
    public function filter(Builder $query, $relation, $operator, $value, $boolean = 'and', Closure $callback = null)
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
        $resource = $this->types[0];

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
            'resources'   => $this->types,
            'relation'    => $this->relation,
            'constraints' => [
                'requiredOnCreation' => $this->isRequiredOnCreation($request),
            ],
            'name' => $this->name(),
        ];
    }
}
