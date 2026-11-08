<?php

namespace Lomkit\Rest\Scramble\Types;

use Dedoc\Scramble\Support\Generator\Types\Type;

final class Nothing extends Type
{
    public function __construct()
    {
        parent::__construct('nothing');
    }

    #[\Override]
    public function serializeAs31(): mixed
    {
        return false;
    }

    #[\Override]
    public function serializeAs32(): mixed
    {
        return false;
    }
}
