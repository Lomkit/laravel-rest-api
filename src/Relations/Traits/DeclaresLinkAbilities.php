<?php

namespace Lomkit\Rest\Relations\Traits;

trait DeclaresLinkAbilities
{
    private ?string $attachAbility = null;

    private ?string $detachAbility = null;

    public function abilities(?string $attach = null, ?string $detach = null): static
    {
        $this->attachAbility = $attach;
        $this->detachAbility = $detach;

        return $this;
    }

    public function linkAbility(bool $detach): ?string
    {
        return $detach ? $this->detachAbility : $this->attachAbility;
    }
}
