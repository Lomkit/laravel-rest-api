<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

use Illuminate\Support\Carbon;
use Lomkit\Rest\Fields\DateTime;

class UtcDateTime extends DateTime
{
    #[\Override]
    protected function normalize(Carbon $date): Carbon
    {
        return $date->utc();
    }
}
