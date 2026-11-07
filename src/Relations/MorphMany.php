<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class MorphMany extends HasOneOrMany
{
    use HasMultipleResults;
}
