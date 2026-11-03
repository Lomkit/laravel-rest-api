<?php

namespace Lomkit\Rest\Http\Serialization;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final readonly class PlainEnvelope implements Envelope
{
    /** @param Collection<int, Model>|Model $rows */
    public function __construct(private Collection|Model $rows) {}

    #[\Override]
    public function wrap(Closure $row, array $meta): array
    {
        return [
            'data' => $this->rows instanceof Model ? $row($this->rows) : array_map($row, $this->rows->all()),
            'meta' => $meta,
        ];
    }
}
