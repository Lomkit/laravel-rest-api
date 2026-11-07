<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\RowCount;

class MeasuredFullTextPostResource extends FullTextPostResource
{
    #[\Override]
    public function metrics(): array
    {
        return [RowCount::make()];
    }
}
