<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Authorization\UserReference;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Query\Trashed;

final readonly class ActionRunner
{
    /** @param array<string, mixed> $given */
    private function __construct(
        private Action $action,
        private ActionFields $fields,
        private array $given,
        private ?Trashed $trashed,
        private bool $perimeter,
        private ?UserReference $user,
        private ?PendingBatch $batch,
    ) {}

    public static function for(OperateRequest $request, Action $action, ActionFields $fields, bool $perimeter): self
    {
        $payload = $request->payload();

        return new self(
            $action,
            $fields,
            $payload->fields->given,
            $payload->search()->trashed,
            $perimeter,
            $action instanceof ShouldQueue ? UserReference::of(Auth::getDefaultDriver(), $request->user()) : null,
            $action instanceof BatchableAction ? self::batch($action, $fields) : null,
        );
    }

    /** @param Collection<array-key, Model> $models */
    public function run(Collection $models): int
    {
        if ($models->isEmpty() && $this->action->target !== ActionTarget::None) {
            return 0;
        }

        $this->hand($models);

        return $models->count();
    }

    public function finish(): void
    {
        $this->batch?->dispatch();
    }

    /** @param Collection<array-key, Model> $models */
    private function hand(Collection $models): void
    {
        if ($this->user === null) {
            DB::transaction(fn () => $this->action->handle($this->fields, $models));

            return;
        }

        $job = QueuedAction::of($this->action, $this->given, QueuedRows::of($models->all(), $this->trashed, $this->perimeter), $this->user);

        if ($this->batch !== null) {
            $this->batch->add([$job]);

            return;
        }

        Bus::dispatch($job);
    }

    private static function batch(Action $action, ActionFields $fields): PendingBatch
    {
        $batch = (new QueueSettings($action))->configureBatch(Bus::batch([])->name($action->uriKey()));

        $action->withBatch($fields, $batch);

        return $batch;
    }
}
