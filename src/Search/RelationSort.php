<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;

final readonly class RelationSort implements SortNode
{
    public function __construct(
        public Relation $relation,
        public Field $field,
        private ?SortDirection $direction,
    ) {}

    public static function resolve(Resource $resource, string $path, ?SortDirection $direction): self
    {
        [$name, $field] = explode('.', $path, 2);
        $relation = $resource->relation($name) ?? throw InvalidFieldException::notSortable($path);
        $related = $relation->resource();

        return new self($relation, $related->getFields()->get($field), $direction);
    }

    public function path(): string
    {
        return $this->relation->relation.'.'.$this->field->name;
    }

    #[\Override]
    public function direction(): SortDirection
    {
        return $this->direction ?? SortDirection::Asc;
    }
}
