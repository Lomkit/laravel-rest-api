<?php

namespace Lomkit\Rest\Concerns\Relations;

trait HasPivotFields
{
    protected array $pivotFields = [];
    protected array $pivotRules = [];

    public function getPivotFields() {
        return $this->pivotFields;
    }

    public function withPivotFields(array $pivotFields) {
        return tap($this, function () use ($pivotFields) {
            $this->pivotFields = $pivotFields;
        });
    }

    public function withPivotRules(array $pivotRules) {
        return tap($this, function () use ($pivotRules) {
            $this->pivotRules = $pivotRules;
        });
    }

    /**
     * @return array
     */
    public function getPivotRules(): array
    {
        return $this->pivotRules;
    }
}