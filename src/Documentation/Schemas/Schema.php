<?php

namespace Lomkit\Rest\Documentation\Schemas;

abstract class Schema implements \JsonSerializable
{
    /**
     * Generate the current schema for automatic documentation.
     */
    abstract public function generate(): Schema;
}
