<?php

namespace Lomkit\Rest\Instructions;

use http\Exception\RuntimeException;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\Fieldable;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Metable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Http\Requests\RestRequest;

class Instruction
{
    use Makeable;
    use Metable;
    use Fieldable;
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
        $request = app()->make(RestRequest::class);

        return [
            'name'   => $this->name(),
            'uriKey' => $this->uriKey(),
            'fields' => $this->fields($request),
            'meta'   => $this->meta(),
        ];
    }

    /**
     * Perform the instruction on the given query.
     *
     * @param array                                 $fields
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return void
     */
    // TODO(upgrade): change $query type-hint to \Illuminate\Contracts\Database\Eloquent\Builder
    public function handle(array $fields, \Illuminate\Database\Eloquent\Builder $query)
    {
        // ...
    }

    /**
     * Perform the instruction on the scout query.
     *
     * @param array                  $fields
     * @param \Laravel\Scout\Builder $query
     *
     * @return void
     */
    public function handleScout(array $fields, \Laravel\Scout\Builder $query)
    {
        throw new RuntimeException('Not implemented');
    }
}
