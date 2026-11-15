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
    /** @param non-empty-array<string, Extension&ObservesWrites> $extensions */
    private function __construct(
        private Resource $resource,
        private array $extensions,
    ) {}

    public static function of(Resource $resource): ?self
    {
        $extensions = $resource->context()->extensions()->applicable($resource::class, ObservesWrites::class);

        if ($extensions === []) {
            return null;
        }

        ksort($extensions);

        return new self($resource, $extensions);
    }

    /**
     * @param  list<OperationEntry>  $operations
     * @param  list<WrittenRow>  $written
     */
    public function mutated(WriteTransaction $transaction, array $operations, array $written): void
    {
        $entries = array_map(fn (OperationEntry $entry): array => $entry->toArray(), $operations);

        $this->schedule($transaction, fn (?Authenticatable $user): WriteObservationContext => new WriteObservationContext(
            $this->resource, Endpoint::Mutate, $user, operations: $entries, written: self::copied($written),
        ));
    }

    /** @param Collection<int, Model> $rows */
    public function rowsWritten(WriteTransaction $transaction, Endpoint $endpoint, Collection $rows): void
    {
        $this->schedule($transaction, fn (?Authenticatable $user): WriteObservationContext => new WriteObservationContext(
            $this->resource, $endpoint, $user, rows: RowCopies::of($rows),
        ));
    }

    /** @param list<Model>|null $rows */
    public function operated(WriteTransaction $transaction, Action $action, ActionFields $fields, int $impacted, ?array $rows): void
    {
        $this->schedule($transaction, fn (?Authenticatable $user): WriteObservationContext => new WriteObservationContext(
            $this->resource, Endpoint::Operate, $user, rows: $rows === null ? null : RowCopies::of($rows), action: clone $action, fields: $fields, impacted: $impacted,
        ));
    }

    /** @param Closure(?Authenticatable): WriteObservationContext $context */
    private function schedule(WriteTransaction $transaction, Closure $context): void
    {
        $user = $this->resource->context()->actingUser()->user;
        $calls = [];

        foreach ($this->extensions as $extension) {
            $calls[] = [$extension, $context($user)];
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
     * @return list<WrittenRow>
     */
    private static function copied(array $written): array
    {
        $models = RowCopies::of(array_map(fn (WrittenRow $row): Model => $row->model, $written));

        return array_map(fn (WrittenRow $row, Model $model): WrittenRow => new WrittenRow($row->resource, $row->entry, $model), $written, $models);
    }
}
