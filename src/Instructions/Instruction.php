<?php

namespace Lomkit\Rest\Instructions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Fields\Concerns\HasInputFields;
use Lomkit\Rest\Fields\Details\InputFieldDetails;
use Lomkit\Rest\Http\Requests\RestRequest;

/** @phpstan-consistent-constructor */
class Instruction
{
    use HasInputFields;
    use HasMeta;
    use Makeable;
    use Resourcable;

    public ?string $name = null;

    public function name(): string
    {
        return $this->name ?: Str::of(class_basename(static::class))->beforeLast('Instruction')->snake(' ')->title()->toString();
    }

    public function uriKey(): string
    {
        return Str::slug($this->name(), '-', null);
    }

    public function request(): RestRequest
    {
        return $this->resource->request();
    }

    /** @return array{name: string, uriKey: string, fields: list<array<string, mixed>>, meta: \stdClass, text: bool} */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'fields' => InputFieldDetails::forFields($this->getFields()),
            'meta' => (object) $this->getMeta(),
            'text' => InstructionSupport::of($this)->runsWithText($this->resource->context()->text($this->resource)->backend),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Builder<Model>  $query
     */
    public function handle(array $fields, Builder $query): void {}
}
