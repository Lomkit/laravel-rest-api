<?php

namespace Lomkit\Rest\Fields;

use Illuminate\Support\Carbon;

class DateTime extends Date
{
    #[\Override]
    public function type(): string
    {
        return 'datetime';
    }

    #[\Override]
    protected function normalize(Carbon $date): Carbon
    {
        return $date;
    }
}
