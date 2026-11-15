<?php

namespace Lomkit\Rest\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\WriteGuard;
use Lomkit\Rest\Http\WriteTransaction;

/** @internal */
final readonly class ActionWrites
{
    private function __construct(
        private Action $action,
        private ActionFields $fields,
        private WriteTransaction $transaction,
        private ?WriteGuard $guard,
    ) {}

    public static function of(Action $action, ActionFields $fields, Resource $resource): self
    {
        return new self($action, $fields, WriteTransaction::of($resource), WriteGuard::of($resource));
    }

    /** @param Closure(): int $run */
    public function around(Closure $run): int
    {
        if ($this->action->transaction !== ActionTransaction::Whole) {
            return $run();
        }

        return $this->transaction->run(function () use ($run): int {
            $this->guard?->action($this->action, $this->fields, null);

            return $run();
        });
    }

    /** @param Collection<array-key, Model> $models */
    public function handle(Collection $models): void
    {
        if ($this->action->transaction === ActionTransaction::Row) {
            foreach ($models as $row) {
                $this->action->beforeRow($this->fields, $row);
                $this->transacted([$row], fn () => $this->action->handleRow($this->fields, $row));
            }

            return;
        }

        if ($this->action->transaction === ActionTransaction::Chunk) {
            $this->transacted(array_values($models->all()), fn () => $this->action->handle($this->fields, $models));

            return;
        }

        if ($this->action->transaction === ActionTransaction::None) {
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
    }

    /** @param Collection<array-key, Model> $models */
    private function direct(Collection $models): void
    {
        $this->guard?->action($this->action, $this->fields, array_values($models->all()));
        $this->action->handle($this->fields, $models);
    }
}
