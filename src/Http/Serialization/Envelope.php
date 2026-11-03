<?php

namespace Lomkit\Rest\Http\Serialization;

use Closure;
use Illuminate\Database\Eloquent\Model;

interface Envelope
{
    /**
     * @param  Closure(Model): array<string, mixed>  $row
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function wrap(Closure $row, array $meta): array;
}
