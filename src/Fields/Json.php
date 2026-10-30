<?php

namespace Lomkit\Rest\Fields;

class Json extends Field
{
    #[\Override]
    public function operators(): array
    {
        return [];
    }

    #[\Override]
    public function baseRules(): array
    {
        return ['array'];
    }

    #[\Override]
    public function type(): string
    {
        return 'json';
    }

    #[\Override]
    protected function sortableByDefault(): bool
    {
        return false;
    }

    #[\Override]
    protected function filterableByDefault(): bool
    {
        return false;
    }
}
