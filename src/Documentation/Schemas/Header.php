<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Header extends Parameter
{
    /**
     * Set the name for a header (not allowed).
     *
     * @param  string  $name
     * @return Parameter
     * @throws \RuntimeException
     */
    public function withName(string $name): Parameter
    {
        throw new \RuntimeException('Name is forbidden on headers');
    }
}