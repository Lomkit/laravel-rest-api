<?php

namespace Lomkit\Rest\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\WriteTransaction;

/** @internal */
final readonly class ActionWrites
{
    private function __construct(
        private Action $action,
        private ActionFields $fields,
        private WriteTransaction $transaction,
    ) {}

    public static function of(Action $action, ActionFields $fields, Resource $resource): self
    {
        return new self($action, $fields, WriteTransaction::of($resource));
    }

    /** @param Closure(): int $run */
    public function around(Closure $run): int
    {
        if ($this->action->transaction !== ActionTransaction::Whole) {
            return $run();
        }

        return $this->transaction->run($run);
    }

    /** @param Collection<array-key, Model> $models */
    public function handle(Collection $models): void
    {
        if ($this->action->transaction === ActionTransaction::Row) {
            foreach ($models as $row) {
                $this->action->beforeRow($this->fields, $row);
                $this->transaction->over([$row])->run(fn () => $this->action->handleRow($this->fields, $row));
            }

            return;
        }

        if ($this->action->transaction === ActionTransaction::Chunk) {
            $this->transaction->over($models)->run(fn () => $this->action->handle($this->fields, $models));

            return;
        }

        $this->action->handle($this->fields, $models);
    }
}
