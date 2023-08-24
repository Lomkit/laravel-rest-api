<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Header extends Parameter
{
    public function withName(string $name): Parameter
    {
        throw new \RuntimeException('Name is forbidden on headers');
    }
}