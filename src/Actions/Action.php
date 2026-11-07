<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JsonSerializable;
use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Fields\Concerns\HasInputFields;
use Lomkit\Rest\Fields\Details\InputFieldDetails;
use Lomkit\Rest\Http\OperateResponse;
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

    public bool $destructive = false;

    public ?string $confirm = null;

    public bool $skipUnauthorized = false;

    public bool $atomic = false;

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

    public function destructive(): static
    {
        $this->destructive = true;

        return $this;
    }

    public function confirm(string $text): static
    {
        $this->confirm = $text;

        return $this;
    }

    public function skipUnauthorized(): static
    {
        $this->skipUnauthorized = true;

        return $this;
    }

    public function atomic(): static
    {
        $this->atomic = true;

        return $this;
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return ['viewAny'];
    }

    public function authorizeEach(): ?string
    {
        return null;
    }

    public function ensureValid(): static
    {
        $ability = $this->authorizeEach();

        if ($ability === null && $this->skipUnauthorized) {
            throw InvalidActionException::skipWithoutAbility(static::class);
        }

        if ($ability !== null && $this->target === ActionTarget::None) {
            throw InvalidActionException::abilityWithoutRows(static::class, $ability);
        }

        if ($this->atomic && $this instanceof ShouldQueue) {
            throw InvalidActionException::atomicQueued(static::class);
        }

        return $this;
    }

    /** @return array{name: string, uriKey: string, target: value-of<ActionTarget>, fields: list<array<string, mixed>>, meta: \stdClass, destructive: bool, confirm: ?string} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'target' => $this->target->value,
            'fields' => InputFieldDetails::forFields($this->resource->definition()->inputFields($this)),
            'meta' => (object) $this->getMeta(),
            'destructive' => $this->destructive,
            'confirm' => $this->confirm,
        ];
    }

    /** @param Collection<array-key, Model> $models */
    public function handle(ActionFields $fields, Collection $models): void {}

    public function withBatch(ActionFields $fields, PendingBatch $batch): void {}

    public function respond(ActionFields $fields, int $impacted): ?ActionResponse
    {
        return null;
    }

    public function handleRequest(OperateRequest $request): OperateResponse
    {
        $fields = ActionFields::of($this, $request->payload()->fields, Auth::getDefaultDriver(), $request->user());
        $response = (new DispatchAction($request, $this, $fields))->dispatch();

        return $response->answer($this->respond($fields, $response->impacted));
    }
}
