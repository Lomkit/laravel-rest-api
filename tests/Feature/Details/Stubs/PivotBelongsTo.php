<?php

namespace Lomkit\Rest\Tests\Feature\Details\Stubs;

use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Relations\BelongsTo;

class PivotBelongsTo extends BelongsTo implements PivotRelation
{
    use HasPivotFields;
}
