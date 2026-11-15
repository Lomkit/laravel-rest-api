<?php

namespace Lomkit\Rest\Scramble\Components;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;

/** @internal */
final class MetaSchemaRecord
{
    public ?ObjectType $search = null;

    public ?ObjectType $details = null;
}
