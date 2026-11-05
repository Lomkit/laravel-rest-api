<?php

namespace Lomkit\Rest\Actions;

trait Actionable
{
    /** @return array<int, Action> */
    public function actions(): array
    {
        return [];
    }
}
