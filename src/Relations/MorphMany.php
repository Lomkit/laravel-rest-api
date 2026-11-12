<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Contracts\SyncableRelation;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;
use Lomkit\Rest\Relations\Traits\SyncsChildren;

class MorphMany extends HasOneOrMany implements SyncableRelation
{
    use HasMultipleResults;
    use SyncsChildren;
}
