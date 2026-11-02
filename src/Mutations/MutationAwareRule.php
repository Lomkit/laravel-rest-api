<?php

namespace Lomkit\Rest\Mutations;

interface MutationAwareRule
{
    public function setMutation(MutationContext $mutation): static;
}
