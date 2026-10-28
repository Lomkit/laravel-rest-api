<?php

namespace Lomkit\Rest\Fields;

use Illuminate\Support\Carbon;

class DateTime extends Date
{
    public function type(): string
    {
        return 'datetime';
    }

    protected function normalize(Carbon $date): Carbon
    {
        return $date;
    }
}
