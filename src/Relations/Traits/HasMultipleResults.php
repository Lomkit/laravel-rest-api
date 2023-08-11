<?php

namespace Lomkit\Rest\Relations\Traits;

trait HasMultipleResults
{
    public function hasMultipleEntries() {
        return true;
    }
}