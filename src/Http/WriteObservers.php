<?php

namespace Lomkit\Rest\Http;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ObservesWrites;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\OperationEntry;
use Lomkit\Rest\Mutations\WrittenRow;
use Throwable;

/** @internal */
final readonly class WriteObservers
{
    private function __construct(
        private Resource $resource,
    ) {}

    public static function of(Resource $resource): ?self
    {
        return $resource->context()->extensions()->hasKind(ObservesWrites::class) ? new self($resource) : null;
    }

    /**
     * @param  list<OperationEntry>  $operations
     * @param  list<WrittenRow>  $written
     */
    public function mutated(WriteTransaction $transaction, array $operations, array $written): void
    {
        $entries = array_map(fn (OperationEntry $entry): array => $entry->toArray(), $operations);
        $calls = [];

        foreach ($this->byResource($written) as [$resource, $rows]) {
            $calls = [...$calls, ...$this->calls($resource, fn (?Authenticatable $user): WriteObservationContext => new WriteObservationContext(
                $resource, Endpoint::Mutate, $user, operations: $entries, written: self::copied($rows),
            ))];
        }

        $this->schedule($transaction, $calls);
    }

    /** @param Collection<int, Model> $rows */
    public function rowsWritten(WriteTransaction $transaction, Endpoint $endpoint, Collection $rows): void
    {
        $this->schedule($transaction, $this->calls($this->resource, fn (?Authenticatable $user): WriteObservationContext => new WriteObservationContext(
            $this->resource, $endpoint, $user, rows: RowCopies::of($rows),
        )));
    }

    /** @param list<Model>|null $rows */
    public function operated(WriteTransaction $transaction, Action $action, ActionFields $fields, int $impacted, ?array $rows): void
    {
        $this->schedule($transaction, $this->calls($this->resource, fn (?Authenticatable $user): WriteObservationContext => new WriteObservationContext(
            $this->resource, Endpoint::Operate, $user, rows: $rows === null ? null : RowCopies::of($rows), action: clone $action, fields: $fields, impacted: $impacted,
        )));
    }

    /**
     * @param  Closure(?Authenticatable): WriteObservationContext  $context
     * @return list<array{Extension&ObservesWrites, WriteObservationContext}>
     */
    private function calls(Resource $resource, Closure $context): array
    {
        $extensions = $this->resource->context()->extensions()->applicable($resource::class, ObservesWrites::class);
        $user = $this->resource->context()->actingUser()->user;
        $calls = [];

        foreach ($extensions as $extension) {
            $calls[] = [$extension, $context($user)];
        }

        return $calls;
    }

    /** @param list<array{Extension&ObservesWrites, WriteObservationContext}> $calls */
    private function schedule(WriteTransaction $transaction, array $calls): void
    {
        if ($calls === []) {
            return;
        }

        $transaction->afterCommit(static function () use ($calls): void {
            foreach ($calls as [$extension, $observed]) {
                try {
                    $extension->observeWrite($observed);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });
    }

    /**
     * @param  list<WrittenRow>  $written
     * @return list<array{Resource, list<WrittenRow>}>
     */
    private function byResource(array $written): array
    {
        $resources = [$this->resource::class => $this->resource];
        $rows = [$this->resource::class => []];

        foreach ($written as $row) {
            $resources[$row->resource::class] ??= $row->resource;
            $rows[$row->resource::class][] = $row;
        }

        return array_map(fn (Resource $resource, string $class): array => [$resource, $rows[$class] ?? []], $resources, array_keys($resources));
    }

    /**
     * @param  list<WrittenRow>  $written
     * @return list<WrittenRow>
     */
    private static function copied(array $written): array
    {
        $models = RowCopies::of(array_map(fn (WrittenRow $row): Model => $row->model, $written));

        return array_map(fn (WrittenRow $row, Model $model): WrittenRow => new WrittenRow($row->resource, $row->entry, $model), $written, $models);
    }
}
