<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;

interface RowRenderer
{
    /** @return array<string, mixed> */
    public function row(Model $model): array;
}
