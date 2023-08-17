<?php

namespace Lomkit\Rest\Instructions;

use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\Fieldable;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Metable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Query\Builder;

class Instruction
{
    use Makeable, Metable, Fieldable, Resourcable;

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
        return $this->name ?: Str::of(class_basename(get_class($this)))->beforeLast('Instruction')->snake(' ')->title();
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
        $request = app()->make(RestRequest::class);

        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'fields' => $this->fields($request),
            'meta' => $this->meta()
        ];
    }

    /**
     * Perform the instruction on the given query.
     *
     * @param array $fields
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function handle(array $fields, \Illuminate\Database\Eloquent\Builder $query)
    {
        // ...
    }
}