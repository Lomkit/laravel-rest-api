<?php

namespace Lomkit\Rest\Concerns\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
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

        return $this->pivotFieldSet = $fields->bindTo($pivot);
    }

    /** @return list<string> */
    #[\Override]
    public function reservedKeys(): array
    {
        $relation = $this->fromResource::newModel()->{$this->relation}();

        return [Str::snake($relation instanceof BelongsToMany ? $relation->getPivotAccessor() : 'pivot')];
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
