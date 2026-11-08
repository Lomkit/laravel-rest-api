<?php

namespace Lomkit\Rest\Fields\Concerns;

trait ConfiguresExamples
{
    /** @var list<int|float|string|bool|array<mixed>|null> */
    private array $examples = [];

    /** @param int|float|string|bool|array<mixed>|null ...$values */
    public function examples(int|float|string|bool|array|null ...$values): static
    {
        $this->examples = array_values($values);

        return $this;
    }

    /** @return list<int|float|string|bool|array<mixed>|null> */
    public function getExamples(): array
    {
        return $this->examples;
    }
}
