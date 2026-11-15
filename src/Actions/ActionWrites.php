<?php

namespace Lomkit\Rest\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\WriteGuard;
use Lomkit\Rest\Http\WriteObservers;
use Lomkit\Rest\Http\WriteTransaction;

/** @internal */
final readonly class ActionWrites
{
    private function __construct(
        private Action $action,
        private ActionTransaction $mode,
        private ActionFields $fields,
        private WriteTransaction $transaction,
        private ?WriteGuard $guard,
        private ?WriteObservers $observers,
    ) {}

    public static function of(Action $action, ActionFields $fields, Resource $resource): self
    {
        return self::in($action->transaction, $action, $fields, $resource, WriteTransaction::of($resource));
    }

    public static function in(ActionTransaction $mode, Action $action, ActionFields $fields, ?Resource $resource, WriteTransaction $transaction): self
    {
        if ($resource === null) {
            return new self($action, $mode, $fields, $transaction, null, null);
        }

        return new self($action, $mode, $fields, $transaction, WriteGuard::of($resource), WriteObservers::of($resource));
    }

    /** @param Closure(): int $run */
    public function around(Closure $run): int
    {
        if ($this->mode !== ActionTransaction::Whole) {
            return $run();
        }

        $impacted = $this->transaction->run(function () use ($run): int {
            $this->guard?->action($this->action, $this->fields, null);

            return $run();
        });
        $this->observers?->operated($this->transaction, $this->action, $this->fields, $impacted, null);

        return $impacted;
    }

    /** @param Collection<array-key, Model> $models */
    public function handle(Collection $models): void
    {
        if ($this->mode === ActionTransaction::Row) {
            foreach ($models as $row) {
                $this->action->beforeRow($this->fields, $row);
                $this->transacted([$row], fn () => $this->action->handleRow($this->fields, $row));
            }

            return;
        }

        if ($this->mode === ActionTransaction::Chunk) {
            $this->transacted(array_values($models->all()), fn () => $this->action->handle($this->fields, $models));

            return;
        }

        if ($this->mode === ActionTransaction::None) {
            $this->direct($models);

            return;
        }

        $this->action->handle($this->fields, $models);
    }

    /**
     * @param  list<Model>  $rows
     * @param  Closure(): void  $write
     */
    private function transacted(array $rows, Closure $write): void
    {
        $this->transaction->over($rows)->run(function () use ($rows, $write): void {
            $this->guard?->action($this->action, $this->fields, $rows);
            $write();
        });
        $this->observers?->operated($this->transaction, $this->action, $this->fields, count($rows), $rows);
    }

    /** @param Collection<array-key, Model> $models */
    private function direct(Collection $models): void
    {
        $rows = array_values($models->all());

        $this->guard?->action($this->action, $this->fields, $rows);
        $this->action->handle($this->fields, $models);
        $this->observers?->operated($this->transaction, $this->action, $this->fields, count($rows), $rows);
    }
}
