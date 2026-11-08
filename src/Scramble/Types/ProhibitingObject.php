<?php

namespace Lomkit\Rest\Scramble\Types;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;

final class ProhibitingObject extends ObjectType
{
    /** @param non-empty-list<string> $prohibited */
    public function __construct(
        public array $prohibited,
    ) {
        parent::__construct();
    }

    #[\Override]
    public function serializeAs31(): mixed
    {
        return [...(array) parent::serializeAs31(), 'not' => $this->not()];
    }

    #[\Override]
    public function serializeAs32(): mixed
    {
        return [...(array) parent::serializeAs32(), 'not' => $this->not()];
    }

    /** @return array<string, mixed> */
    private function not(): array
    {
        if (count($this->prohibited) === 1) {
            return ['required' => $this->prohibited];
        }

        return ['anyOf' => array_map(fn (string $key): array => ['required' => [$key]], $this->prohibited)];
    }
}
