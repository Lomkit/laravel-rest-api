<?php

namespace Lomkit\Rest\Fields;

final readonly class NarrowedFields
{
    /**
     * @internal
     *
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
