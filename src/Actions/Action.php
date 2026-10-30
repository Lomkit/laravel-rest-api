<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonSerializable;
use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Exceptions\InvalidActionStateException;
use Lomkit\Rest\Fields\Concerns\HasInputFields;
use Lomkit\Rest\Fields\Details\InputFieldDetails;
use Lomkit\Rest\Http\Requests\OperateRequest;

/** @phpstan-consistent-constructor */
class Action implements JsonSerializable
{
    use Batchable;
    use Dispatchable;
    use HasInputFields;
    use HasMeta;
    use InteractsWithQueue;
    use Makeable;
    use Queueable;
    use Resourcable;
    use SerializesModels;

    public ?string $name = null;

    public bool $standalone = false;

    public bool $targeted = false;

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

    public function standalone(): static
    {
        if ($this->targeted) {
            throw new InvalidActionStateException('An action cannot be both standalone and targeted.');
        }

        $this->standalone = true;

        return $this;
    }

    public function isStandalone(): bool
    {
        return $this->standalone;
    }

    public function targeted(): static
    {
        if ($this->standalone) {
            throw new InvalidActionStateException('An action cannot be both standalone and targeted.');
        }

        $this->targeted = true;

        return $this;
    }

    public function isTargeted(): bool
    {
        return $this->targeted;
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return ['viewAny'];
    }

    /** @return array{name: string, uriKey: string, fields: list<array<string, mixed>>, meta: array<string, mixed>, standalone: bool, targeted: bool} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'fields' => InputFieldDetails::forFields($this->getFields($this->resource->context()->request)),
            'meta' => $this->getMeta(),
            'standalone' => $this->isStandalone(),
            'targeted' => $this->isTargeted(),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<array-key, Model>  $models
     */
    public function handle(array $fields, Collection $models): void {}

    /** @param array<string, mixed> $fields */
    public function withBatch(array $fields, PendingBatch $batch): void {}

    public function handleRequest(OperateRequest $request): int
    {
        return (new DispatchAction($request, $this, $request->resolveFields($this)))->dispatch($this->chunkCount);
    }
}
