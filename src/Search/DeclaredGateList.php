<?php

namespace Lomkit\Rest\Search;

use Closure;
use LogicException;

/** @internal */
final readonly class DeclaredGateList
{
    /**
     * @param  array<mixed>  $names
     * @param  Closure(int, mixed): LogicException  $refuse
     */
    public function __construct(public array $names, private Closure $refuse) {}

    public function refuse(int $index, mixed $name): LogicException
    {
        return ($this->refuse)($index, $name);
    }
}
