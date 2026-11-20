<?php

namespace Lomkit\Rest\Scramble\Types;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;

/** @internal */
final class NonEmptyObject extends ObjectType
{
    #[\Override]
    public function serializeAs31(): mixed
    {
        return [...(array) parent::serializeAs31(), 'minProperties' => 1];
    }

    #[\Override]
    public function serializeAs32(): mixed
    {
        return [...(array) parent::serializeAs32(), 'minProperties' => 1];
    }
}
