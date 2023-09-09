<?php

namespace Lomkit\Rest\Concerns\Relations;

trait HasPivotFields
{
    protected array $pivotFields = [];
    protected array $pivotRules = [];

    /**
     * Get the pivot fields.
     *
     * @return array
     */
    public function getPivotFields() {
        return $this->pivotFields;
    }

    /**
     * Set the pivot fields.
     *
     * @param array $pivotFields
     * @return $this
     */
    public function withPivotFields(array $pivotFields) {
        return tap($this, function () use ($pivotFields) {
            $this->pivotFields = $pivotFields;
        });
    }

    /**
     * Set the pivot rules.
     *
     * @param array $pivotRules
     * @return $this
     */
    public function withPivotRules(array $pivotRules) {
        return tap($this, function () use ($pivotRules) {
            $this->pivotRules = $pivotRules;
        });
    }

    /**
     * Get the pivot rules.
     *
     * @return array
     */
    public function getPivotRules(): array
    {
        return $this->pivotRules;
    }
}