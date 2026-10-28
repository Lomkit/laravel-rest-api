<?php

namespace Lomkit\Rest\Instructions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Scout\Builder as ScoutBuilder;
use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Fields\Concerns\HasInputFields;
use Lomkit\Rest\Fields\Details\InputFieldDetails;

/** @phpstan-consistent-constructor */
class Instruction
{
    use HasInputFields;
    use HasMeta;
    use Makeable;
    use Resourcable;

    /** @var string|null */
    public $name;

    /** @return string */
    public function name()
    {
        return $this->name ?: Str::of(class_basename(static::class))->beforeLast('Instruction')->snake(' ')->title()->toString();
    }

    /** @return string */
    public function uriKey()
    {
        return Str::slug($this->name(), '-', null);
    }

    /** @return array{name: string, uriKey: string, fields: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'fields' => InputFieldDetails::forFields($this->getFields($this->resource->context()->request)),
            'meta' => $this->getMeta(),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Builder<Model>  $query
     * @return void
     */
    public function handle(array $fields, Builder $query) {}

    /**
     * @param  array<string, mixed>  $fields
     * @param  ScoutBuilder<Model>  $query
     * @return void
     */
    public function handleScout(array $fields, ScoutBuilder $query)
    {
        throw InvalidInstructionException::scoutNotSupported(static::class);
    }
}
