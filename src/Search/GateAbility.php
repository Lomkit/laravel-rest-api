<?php

namespace Lomkit\Rest\Search;

use Illuminate\Support\Str;
use Lomkit\Rest\Authorization\Ability;

final readonly class GateAbility
{
    public function __construct(public Ability $ability) {}

    public function name(): string
    {
        return $this->ability->value;
    }

    public function key(): string
    {
        return 'authorized_to_'.Str::snake($this->name());
    }

    public function isClassLevel(): bool
    {
        return $this->ability->isClassLevel();
    }
}
