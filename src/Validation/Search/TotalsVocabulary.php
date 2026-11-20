<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Search\Totals\MeasureType;

/** @internal */
final readonly class TotalsVocabulary
{
    public const string PROHIBITED = 'validation.prohibited';

    public const string UNKNOWN = 'validation.in';

    /**
     * @param  array<string, Field>  $groups
     * @param  array<string, Field>  $measures
     * @param  array<string, list<MeasureType>>  $types
     */
    private function __construct(
        public array $groups,
        public array $measures,
        public ?string $refusal,
        public array $types = [],
        public string $uncomputed = self::UNKNOWN,
        public ?int $limit = null,
    ) {}

    public static function refused(string $refusal): self
    {
        return new self([], [], $refusal);
    }

    /**
     * @param  array<string, Field>  $groups
     * @param  array<string, Field>  $measures
     * @param  array<string, list<MeasureType>>|null  $types
     */
    public static function of(array $groups, array $measures, ?array $types = null, string $uncomputed = self::UNKNOWN, ?int $limit = null): self
    {
        return new self($groups, $measures, null, $types ?? array_map(static fn (Field $field): array => MeasureType::cases(), $measures), $uncomputed, $limit);
    }

    public function measures(string $field, MeasureType $type): bool
    {
        return in_array($type, $this->types[$field] ?? [], true);
    }
}
