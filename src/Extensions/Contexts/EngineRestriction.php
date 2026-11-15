<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\RestrictingExtension;

final readonly class EngineRestriction
{
    /** @internal */
    public function __construct(
        private EngineQuery $query,
        private RestrictingExtension $extension,
    ) {}

    public function where(string $attribute, FilterOperator|string $operator, mixed $value): self
    {
        $this->query->where($attribute, $operator, $value);

        return $this;
    }

    /** @param array<mixed> $values */
    public function whereIn(string $attribute, array $values): self
    {
        $this->query->restrictIn($attribute, $values, $this->extension);

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
