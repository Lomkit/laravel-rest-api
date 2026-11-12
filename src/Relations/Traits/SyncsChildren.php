<?php

namespace Lomkit\Rest\Relations\Traits;

use Lomkit\Rest\Relations\ChildRemoval;

trait SyncsChildren
{
    private ?ChildRemoval $syncRemoval = null;

    public function syncable(ChildRemoval $removal): static
    {
        $this->syncRemoval = $removal;

        return $this;
    }

    public function syncRemoval(): ?ChildRemoval
    {
        return $this->syncRemoval;
    }
}
