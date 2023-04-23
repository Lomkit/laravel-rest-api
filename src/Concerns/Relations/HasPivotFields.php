<?php

namespace Lomkit\Rest\Concerns\Relations;

trait HasPivotFields
{
    protected array $pivotFields = [];

    public function getPivotFields() {
        return $this->pivotFields;
    }

    public function withPivotFields(array $pivotFields) {
        return tap($this, function () use ($pivotFields) {
            $this->pivotFields = $pivotFields;
        });
    }
}