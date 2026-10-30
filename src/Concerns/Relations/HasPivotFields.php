<?php

namespace Lomkit\Rest\Concerns\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Rules\Mutate\MutatePivot;
use ReflectionClass;

trait HasPivotFields
{
    private ?FieldSet $pivotFieldSet = null;

    private bool $pivotFieldsBound = false;

    /** @param list<Field> $fields */
    public function pivotFields(array $fields): static
    {
        $this->pivotFieldSet = FieldSet::forPivot($fields, 'pivotFields() of the '.$this->relation.' relation');
        $this->pivotFieldsBound = false;

        return $this;
    }

    public function getPivotFields(): FieldSet
    {
        $fields = $this->pivotFieldSet ??= FieldSet::of([]);

        if ($this->pivotFieldsBound) {
            return $fields;
        }

        $pivot = $this->pivotModel();

        if ($pivot === null) {
            return $fields;
        }

        $this->pivotFieldsBound = true;

        return $fields->bindTo($pivot);
    }

    /** @return array<string, list<mixed>> */
    public function pivotRules(string $prefix): array
    {
        return [$prefix.($this->hasMultipleEntries() ? '.*' : '') => [new MutatePivot($this)]];
    }

    private function pivotModel(): ?Model
    {
        if (! isset($this->fromResource) || ! (new ReflectionClass($this->fromResource::modelClass()))->isInstantiable()) {
            return null;
        }

        $relation = $this->fromResource::newModel()->{$this->relation}();
        $pivot = $relation instanceof BelongsToMany ? $relation->getPivotClass() : Pivot::class;

        return new $pivot;
    }
}
