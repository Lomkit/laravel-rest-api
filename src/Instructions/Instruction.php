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
use Lomkit\Rest\Http\Details\DetailsSelection;
use Lomkit\Rest\Http\Requests\RestRequest;

/** @template TModel of Model = Model */
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

    /** @return array<string, mixed> */
    public function toDetails(DetailsSelection $selection): array
    {
        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            ...$selection->part('instructions.fields', fn (): array => ['fields' => InputFieldDetails::forFields($this->resource->definition()->inputFields($this))]),
            ...$selection->part('instructions.meta', fn (): array => ['meta' => (object) $this->getMeta()]),
            'text' => InstructionSupport::of($this)->runsWithText($this->resource->context()->text($this->resource)->backend),
        ];
    }

    /** @param Builder<TModel> $query */
    public function handle(InstructionFields $fields, Builder $query): void {}

    /** @return array<string, mixed> */
    public function searchMeta(InstructionFields $fields): array
    {
        return [];
    }
}
