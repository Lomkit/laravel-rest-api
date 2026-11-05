<?php

namespace Lomkit\Rest\Concerns\Resource;

trait Rulable
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function createRules(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function updateRules(): array
    {
        return [];
    }
}
