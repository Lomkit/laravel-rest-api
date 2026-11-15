<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\EngineQuery;

final readonly class EngineRestriction
{
    /** @internal */
    public function __construct(private EngineQuery $query) {}

    public function where(string $attribute, FilterOperator|string $operator, mixed $value): self
    {
        $this->query->where($attribute, $operator, $value);

        return $this;
    }

    /** @param array<mixed> $values */
    public function whereIn(string $attribute, array $values): self
    {
        $this->query->whereIn($attribute, $values);

        return $this;
    }

    /** @param array<mixed> $values */
    public function whereNotIn(string $attribute, array $values): self
    {
        $this->query->whereNotIn($attribute, $values);

        return $this;
    }

    public function matchNothing(): void
    {
        $this->query->matchNothing();
    }
}
