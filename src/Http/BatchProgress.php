<?php

namespace Lomkit\Rest\Http;

use Illuminate\Bus\Batch;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Lomkit\Rest\Actions\BatchOwner;
use Lomkit\Rest\Authorization\UserReference;
use Lomkit\Rest\Http\Requests\BatchRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class BatchProgress implements Responsable
{
    private function __construct(private Batch $batch) {}

    public static function of(BatchRequest $request, Resource $resource): self
    {
        $key = $request->route('action');
        $id = $request->route('batch');
        $definition = $resource->definition();

        if (! is_string($key) || ! is_string($id) || ! $definition->actionExists($key)) {
            throw new NotFoundHttpException;
        }

        $batch = Bus::findBatch($id);
        $owner = BatchOwner::of($definition->action($key), UserReference::of(Auth::getDefaultDriver(), $request->user()));

        if ($batch === null || ! $owner->owns($batch)) {
            throw new NotFoundHttpException;
        }

        return new self($batch);
    }

    #[\Override]
    public function toResponse(mixed $request): JsonResponse
    {
        return new JsonResponse(['data' => [
            'id' => $this->batch->id,
            'total_jobs' => $this->batch->totalJobs,
            'pending_jobs' => $this->batch->pendingJobs,
            'failed_jobs' => $this->batch->failedJobs,
            'progress' => $this->batch->progress(),
            'finished' => $this->batch->finished(),
            'cancelled' => $this->batch->cancelled(),
        ]]);
    }
}
