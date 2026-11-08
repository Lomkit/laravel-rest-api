<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Concerns\HasRouteMiddleware;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Fields\Concerns\HasInputFields;
use Lomkit\Rest\Fields\Details\InputFieldDetails;
use Lomkit\Rest\Http\Details\DetailsSelection;
use Lomkit\Rest\Http\OperateResponse;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Routing\Endpoint;
use ReflectionMethod;

/** @phpstan-consistent-constructor */
class Action
{
    use Batchable;
    use HasInputFields;
    use HasMeta;
    use HasRouteMiddleware;
    use InteractsWithQueue;
    use Makeable;
    use Resourcable;

    public ?string $name = null;

    public ActionTarget $target = ActionTarget::Search;

    public int $chunkCount = 100;

    public ?int $maxResources = 1000;

    public bool $destructive = false;

    public ?string $confirm = null;

    public bool $skipUnauthorized = false;

    public ActionTransaction $transaction = ActionTransaction::Chunk;

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

    public function maxResources(?int $max): static
    {
        $this->maxResources = $max;

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
        $this->transaction = ActionTransaction::Whole;

        return $this;
    }

    public function transactionPerRow(): static
    {
        $this->transaction = ActionTransaction::Row;

        return $this;
    }

    public function withoutTransaction(): static
    {
        $this->transaction = ActionTransaction::None;

        return $this;
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return ['viewAny'];
    }

    /** @return list<string> */
    final public function entryAbilities(): array
    {
        $abilities = $this->abilities();

        return $abilities === [] ? [] : array_values(array_unique(['viewAny', ...$this->resource->abilities(Endpoint::Operate), ...$abilities]));
    }

    public function authorizeEach(): ?string
    {
        return null;
    }

    public function confirmation(ActionFields $fields, int $rows): ?ActionConfirmation
    {
        return null;
    }

    final public function requiresConfirmation(): bool
    {
        return (new ReflectionMethod($this, 'confirmation'))->getDeclaringClass()->getName() !== self::class;
    }

    public function ensureValid(): static
    {
        (new ActionDeclaration($this))->check();

        return $this;
    }

    /** @return array<string, mixed> */
    public function toDetails(DetailsSelection $selection): array
    {
        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'target' => $this->target->value,
            ...$selection->part('actions.fields', fn (): array => ['fields' => InputFieldDetails::forFields($this->resource->definition()->inputFields($this))]),
            ...$selection->part('actions.meta', fn (): array => ['meta' => (object) $this->getMeta()]),
            'destructive' => $this->destructive,
            'confirm' => $this->confirm,
            'confirmation' => $this->requiresConfirmation(),
        ];
    }

    /** @param Collection<array-key, Model> $models */
    public function handle(ActionFields $fields, Collection $models): void {}

    public function beforeRow(ActionFields $fields, Model $row): void {}

    public function handleRow(ActionFields $fields, Model $row): void {}

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
