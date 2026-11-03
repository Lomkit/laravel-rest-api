<?php

namespace Lomkit\Rest\Search;

use Illuminate\Support\Str;
use Lomkit\Rest\Authorization\Ability;

final readonly class GateAbility
{
    public Ability|string $ability;

    public function __construct(Ability|string $ability)
    {
        $this->ability = is_string($ability) ? Ability::tryFrom($ability) ?? $ability : $ability;
    }

    public function name(): string
    {
        return $this->ability instanceof Ability ? $this->ability->value : $this->ability;
    }

    public function key(): string
    {
        return 'authorized_to_'.Str::snake($this->name());
    }

    public function isClassLevel(): bool
    {
        return $this->ability instanceof Ability && $this->ability->isClassLevel();
    }
}
