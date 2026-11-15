<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Lomkit\Rest\Actions\Rows\ActionRows;
use Lomkit\Rest\Authorization\UserReference;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Query\Trashed;

final readonly class ActionRunner
{
    /**
     * @param  array<string, mixed>  $given
     * @param  Collection<int, QueuedAction>|null  $held
     */
    private function __construct(
        private Action $action,
        private ActionFields $fields,
        private array $given,
        private ?Trashed $trashed,
        private bool $perimeter,
        private ?UserReference $user,
        private ?PendingBatch $batch,
        private ?RowChecks $checks,
        private ActionWrites $writes,
        private ?Collection $held,
    ) {}

    public static function for(OperateRequest $request, Action $action, ActionFields $fields, bool $perimeter, ?RowChecks $checks): self
    {
        $payload = $request->payload();
        $user = $action instanceof ShouldQueue || $action instanceof BatchableAction ? UserReference::of(Auth::getDefaultDriver(), $request->user()) : null;
        $queued = $action instanceof ShouldQueue ? $user : null;
        $batch = $user !== null && $action instanceof BatchableAction ? self::batch($action, $fields, $user) : null;
        $writes = ActionWrites::of($action, $fields, $request->resource);

        return new self(
            $action,
            $fields,
            $payload->fields->given,
            $payload->search()->trashed,
            $perimeter,
            $queued,
            $batch,
            $checks,
            $writes,
            $queued !== null && $batch === null && $writes->guarded() ? new Collection : null,
        );
    }

    public function through(ActionRows $rows): int
    {
        return $this->writes->around(fn (): int => $this->operate($rows));
    }

    /** @param Collection<array-key, Model> $models */
    public function run(Collection $models): int
    {
        $models = $this->checks?->filter($models) ?? $models;

        if ($models->isEmpty() && $this->action->target !== ActionTarget::None) {
            return 0;
        }

        $this->hand($models);

        return $models->count();
    }

    public function finish(): ?string
    {
        foreach ($this->held ?? [] as $job) {
            Bus::dispatch($job);
        }

        return $this->batch?->dispatch()->id;
    }

    private function operate(ActionRows $rows): int
    {
        $resource = $this->action->resource;

        $resource->operating($this->action, $this->fields);

        $impacted = $rows->each($this->run(...));

        $resource->operated($this->action, $this->fields, $impacted);

        return $impacted;
    }

    /** @param Collection<array-key, Model> $models */
    private function hand(Collection $models): void
    {
        if ($this->user === null) {
            $this->handle($models);

            return;
        }

        $this->writes->queued($models);
        $job = QueuedAction::of($this->action, $this->given, QueuedRows::of($models->all(), $this->trashed, $this->perimeter), $this->user);

        if ($this->batch !== null) {
            $this->batch->add([$job]);

            return;
        }

        if ($this->held !== null) {
            $this->held->push($job);

            return;
        }

        Bus::dispatch($job);
    }

    /** @param Collection<array-key, Model> $models */
    private function handle(Collection $models): void
    {
        $this->writes->handle($models);
    }

    private static function batch(Action $action, ActionFields $fields, UserReference $user): PendingBatch
    {
        $batch = (new QueueSettings($action))->configureBatch(Bus::batch([])->name($action->uriKey()));

        BatchOwner::of($action, $user)->tag($batch);
        $action->withBatch($fields, $batch);

        return $batch;
    }
}
