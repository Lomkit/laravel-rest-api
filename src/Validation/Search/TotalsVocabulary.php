<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Fields\Field;

/** @internal */
final readonly class TotalsVocabulary
{
    public const string PROHIBITED = 'validation.prohibited';

    /**
     * @param  array<string, Field>  $groups
     * @param  array<string, Field>  $measures
     */
    private function __construct(
        public array $groups,
        public array $measures,
        public ?string $refusal,
    ) {}

    public static function refused(string $refusal): self
    {
        return new self([], [], $refusal);
    }

    /**
     * @param  array<string, Field>  $groups
     * @param  array<string, Field>  $measures
     */
    public static function of(array $groups, array $measures): self
    {
        return new self($groups, $measures, null);
    }
}
