<?php

namespace Lomkit\Rest\Fields;

/** @internal */
final readonly class NarrowedFields
{
    /**
     * @param  list<string>  $hidden
     * @param  list<string>  $readOnly
     * @param  list<string>  $unfilterable
     * @param  list<string>  $unsortable
     */
    public function __construct(
        public array $hidden = [],
        public array $readOnly = [],
        public array $unfilterable = [],
        public array $unsortable = [],
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->hidden === [] && $this->readOnly === [] && $this->unfilterable === [] && $this->unsortable === [];
    }

    public function apply(FieldSet $declared): FieldSet
    {
        if ($this->isEmpty()) {
            return $declared;
        }

        $kept = [];

        foreach ($declared as $field) {
            if (! in_array($field->name, $this->hidden, true)) {
                $kept[] = $this->narrow($field);
            }
        }

        return FieldSet::of($kept);
    }

    public function hiddenFrom(FieldSet $declared): FieldSet
    {
        if ($this->hidden === []) {
            return FieldSet::of([]);
        }

        return FieldSet::of(array_values(array_filter(
            iterator_to_array($declared, false),
            fn (Field $field): bool => in_array($field->name, $this->hidden, true),
        )));
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $rules
     * @return array<TKey, mixed>
     */
    public function withoutHidden(array $rules): array
    {
        if ($this->hidden === []) {
            return $rules;
        }

        return array_filter($rules, fn (int|string $key): bool => ! $this->hides((string) $key), ARRAY_FILTER_USE_KEY);
    }

    /** @return list<string> */
    public function unfilterableNames(): array
    {
        return array_values(array_unique([...$this->hidden, ...$this->unfilterable]));
    }

    /** @return list<string> */
    public function unsortableNames(): array
    {
        return array_values(array_unique([...$this->hidden, ...$this->unsortable]));
    }

    private function hides(string $key): bool
    {
        return array_any($this->hidden, static fn (string $field): bool => $key === $field || str_starts_with($key, $field.'.'));
    }

    private function narrow(Field $field): Field
    {
        $readOnly = in_array($field->name, $this->readOnly, true);
        $unfilterable = in_array($field->name, $this->unfilterable, true);
        $unsortable = in_array($field->name, $this->unsortable, true);

        if (! $readOnly && ! $unfilterable && ! $unsortable) {
            return $field;
        }

        $copy = clone $field;

        if ($readOnly) {
            $copy->readonly();
        }

        if ($unfilterable) {
            $copy->filterable(false);
        }

        if ($unsortable) {
            $copy->sortable(false);
        }

        return $copy;
    }
}
