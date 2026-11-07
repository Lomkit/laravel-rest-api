<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JsonSerializable;
use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Fields\Concerns\HasInputFields;
use Lomkit\Rest\Fields\Details\InputFieldDetails;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;

/** @phpstan-consistent-constructor */
class Action implements JsonSerializable
{
    use Batchable;
    use HasInputFields;
    use HasMeta;
    use InteractsWithQueue;
    use Makeable;
    use Resourcable;

    public ?string $name = null;

    public ActionTarget $target = ActionTarget::Search;

    public int $chunkCount = 100;

    public int $maxResources = 1000;

    public function name(): string
    {
        return $this->name ?: Str::of(class_basename(static::class))->beforeLast('Action')->snake(' ')->title()->toString();
    }

    public function uriKey(): string
    {
        return Str::slug($this->name(), '-', null);
    }

    public function request(): RestRequest
    {
        return $this->resource->request();
    }

    public function target(ActionTarget $target): static
    {
        $this->target = $target;

        return $this;
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return ['viewAny'];
    }

    /** @return array{name: string, uriKey: string, target: value-of<ActionTarget>, fields: list<array<string, mixed>>, meta: \stdClass} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'target' => $this->target->value,
            'fields' => InputFieldDetails::forFields($this->resource->definition()->inputFields($this)),
            'meta' => (object) $this->getMeta(),
        ];
    }

    /** @param Collection<array-key, Model> $models */
    public function handle(ActionFields $fields, Collection $models): void {}

    public function withBatch(ActionFields $fields, PendingBatch $batch): void {}

    public function handleRequest(OperateRequest $request): int
    {
        $fields = ActionFields::of($this, $request->payload()->fields, Auth::getDefaultDriver(), $request->user());

        return (new DispatchAction($request, $this, $fields))->dispatch($this->chunkCount);
    }
}
