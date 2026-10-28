<?php

namespace Lomkit\Rest\Fields;

class Json extends Field
{
    public function operators(): array
    {
        return [];
    }

    public function baseRules(): array
    {
        return ['array'];
    }

    public function type(): string
    {
        return 'json';
    }

    protected function sortableByDefault(): bool
    {
        return false;
    }

    protected function filterableByDefault(): bool
    {
        return false;
    }
}
