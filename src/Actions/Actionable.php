<?php

namespace Lomkit\Rest\Actions;

trait Actionable
{
    /** @return array<int, Action|class-string<Action>> */
    public function actions(): array
    {
        return [];
    }
}
