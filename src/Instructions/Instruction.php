<?php

namespace Lomkit\Rest\Instructions;

use http\Exception\RuntimeException;
use Illuminate\Support\Str;
use Laravel\Scout\Builder;
use Lomkit\Rest\Concerns\Fieldable;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Metable;
use Lomkit\Rest\Concerns\Resourcable;

class Instruction
{
    use Fieldable;
    use Makeable;
    use Metable;
    use Resourcable;

    /**
     * The displayable name of the instruction.
     *
     * @var string
     */
    public $name;

    /**
     * Get the name of the instruction.
     *
     * @return string
     */
    public function name()
    {
        return $this->name ?: Str::of(class_basename(get_class($this)))->beforeLast('Instruction')->snake(' ')->title()->toString();
    }

    /**
     * Get the URI key for the instruction.
     *
     * @return string
     */
    public function uriKey()
    {
        return Str::slug($this->name(), '-', null);
    }

    /**
     * Prepare the action for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $request = $this->resource->context()->request;

        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'fields' => $this->fields($request),
            'meta' => $this->meta(),
        ];
    }

    /**
     * Perform the instruction on the given query.
     *
     *
     * @return void
     */
    public function handle(array $fields, \Illuminate\Database\Eloquent\Builder $query)
    {
        // ...
    }

    /**
     * Perform the instruction on the scout query.
     *
     *
     * @return void
     */
    public function handleScout(array $fields, Builder $query)
    {
        throw new RuntimeException('Not implemented');
    }
}
