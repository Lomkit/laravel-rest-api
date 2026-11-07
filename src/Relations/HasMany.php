<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class HasMany extends HasOneOrMany
{
    use HasMultipleResults;
}
