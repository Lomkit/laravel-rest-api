<?php

namespace Lomkit\Rest\Scramble\Types;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\Discriminator;
use Dedoc\Scramble\Support\Generator\Types\Type;

final class OneOf extends Type
{
    /** @param list<Type> $items */
    public function __construct(
        public array $items,
        ?Discriminator $discriminator = null,
    ) {
        parent::__construct('oneOf');

        $this->discriminator = $discriminator;
    }

    #[\Override]
    public function serializeAs31(): mixed
    {
        return $this->serialize((array) parent::serializeAs31(), fn (OpenApiSerializable $item): mixed => $item->serializeAs31());
    }

    #[\Override]
    public function serializeAs32(): mixed
    {
        return $this->serialize((array) parent::serializeAs32(), fn (OpenApiSerializable $item): mixed => $item->serializeAs32());
    }

    /**
     * @param  array<mixed>  $parent
     * @param  callable(OpenApiSerializable): mixed  $serialize
     * @return array<mixed>
     */
    private function serialize(array $parent, callable $serialize): array
    {
        unset($parent['type']);

        $oneOf = [...$parent, 'oneOf' => array_map($serialize, $this->items)];

        return $this->nullable ? ['anyOf' => [$oneOf, ['type' => 'null']]] : $oneOf;
    }
}
