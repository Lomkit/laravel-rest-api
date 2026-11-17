<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\Groups\FilterLeaf;
use Lomkit\Rest\Text\Engine\Profiles\MatchesStringLists;

/** @internal */
final readonly class IndexLeaves
{
    public function __construct(private EngineIndex $index) {}

    public function compare(string $attribute, FilterOperator|string $operator, mixed $value): FilterLeaf
    {
        $declared = $this->index->attributes->declared($attribute);

        return new FilterLeaf($attribute, $declared->comparison($operator), $declared->value($value));
    }

    /** @param array<mixed> $values */
    public function members(string $attribute, FilterOperator $operator, array $values): FilterLeaf|bool
    {
        $declared = $this->compared($attribute);
        $members = $declared->members($operator, $values);

        return $members === [] ? $operator === FilterOperator::NotIn : new FilterLeaf($attribute, $operator, $members, $declared->list);
    }

    public function compared(string $attribute): IndexAttribute
    {
        $declared = $this->index->attributes->declared($attribute);

        if ($declared->list && ! $this->index->profile instanceof MatchesStringLists) {
            throw InvalidEngineQueryException::unmatchedStringList($this->index->searchedClass(), $attribute, $this->index->profile->name());
        }

        return $declared;
    }
}
