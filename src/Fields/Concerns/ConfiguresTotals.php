<?php

namespace Lomkit\Rest\Fields\Concerns;

trait ConfiguresTotals
{
    private bool $groupable = false;

    private bool $measurable = false;

    public function groupable(bool $groupable = true): static
    {
        $this->groupable = $groupable;

        return $this;
    }

    public function isGroupable(): bool
    {
        return $this->groupable;
    }

    public function measurable(bool $measurable = true): static
    {
        $this->measurable = $measurable;

        return $this;
    }

    public function isMeasurable(): bool
    {
        return $this->measurable;
    }
}
