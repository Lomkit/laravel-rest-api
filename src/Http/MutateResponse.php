<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Lomkit\Rest\Confirmation\Confirmation;
use Lomkit\Rest\Confirmation\ConfirmationGate;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\Mutator;
use Lomkit\Rest\Mutations\OperationEntry;
use Lomkit\Rest\Mutations\OperationLog;
use Lomkit\Rest\Mutations\PendingMutation;
use Lomkit\Rest\Mutations\WriteChecks;

final readonly class MutateResponse implements Responsable
{
    /**
     * @param  list<OperationEntry>  $operations
     * @param  list<array<string, mixed>|null>|null  $records
     */
    private function __construct(
        public array $operations,
        public ?array $records,
    ) {}

    public static function of(MutateRequest $request, Resource $resource): self
    {
        $gate = ConfirmationGate::of($request, [Endpoint::Mutate->value], ['mutate', 'response']);
        $guard = WriteGuard::of($resource);
        $observers = WriteObservers::of($resource);
        $transaction = WriteTransaction::of($resource);
        $log = $transaction
            ->over($request->rows()->loaded())
            ->run(fn (): OperationLog => self::write($request, $resource, $gate, $guard, new OperationLog($observers !== null)));
        $operations = $log->entries();
        $observers?->mutated($transaction, $operations, $log->written());
        $resource->context()->events()->dispatch(ResourceMutated::of($resource, $operations));
        $block = $request->responseBlock();

        return new self($operations, $block === null ? null : WrittenRecords::read($resource, $block, $operations)->rows());
    }

    private static function write(MutateRequest $request, Resource $resource, ConfirmationGate $gate, ?WriteGuard $guard, OperationLog $log): OperationLog
    {
        $nodes = $request->payload();
        $checks = new WriteChecks($request->rows());

        $checks->authorize($resource, $nodes);
        $request->versions()->verify();
        $checks->syncs->verify();
        $gate->pass(fn (): ?Confirmation => $resource->mutateConfirmation(new PendingMutation($nodes, $request->rows())));
        $guard?->mutation($nodes, $request->rows()->loaded());

        $resource->mutatingAll($nodes);

        $operations = (new Mutator($request->rows(), $checks))->execute($resource, $nodes, $log);

        $resource->mutatedAll($operations);

        return $log;
    }

    #[\Override]
    public function toResponse(mixed $request): JsonResponse
    {
        return new JsonResponse($this->records === null
            ? ['operations' => $this->operations]
            : ['operations' => $this->operations, 'records' => $this->records]);
    }
}
