<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use JsonSerializable;
use Lomkit\Rest\Search\Totals\MeasureType;

/** @internal */
final readonly class TotalsGrammar implements JsonSerializable
{
    /**
     * @param  list<string>  $groups
     * @param  array<string, list<MeasureType>>  $measures
     */
    public function __construct(
        public array $groups = [],
        public array $measures = [],
    ) {}

    public function declares(): bool
    {
        return $this->groups !== [] || $this->measures !== [];
    }

    /** @param list<string> $names */
    public function without(array $names): self
    {
        return new self(array_values(array_diff($this->groups, $names)), array_diff_key($this->measures, array_flip($names)));
    }

    /** @return array{groups: list<string>, measures: object} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'groups' => $this->groups,
            'measures' => (object) array_map(static fn (array $types): array => array_map(static fn (MeasureType $type): string => $type->value, $types), $this->measures),
        ];
    }
}
