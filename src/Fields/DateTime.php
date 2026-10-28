<?php

namespace Lomkit\Rest\Fields;

class DateTime extends Date
{
    public function type(): string
    {
        return 'datetime';
    }
}
