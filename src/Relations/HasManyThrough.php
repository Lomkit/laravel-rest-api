<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class HasManyThrough extends HasOneOrManyThrough
{
    use HasMultipleResults;
}
