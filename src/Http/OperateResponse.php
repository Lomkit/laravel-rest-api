<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Lomkit\Rest\Actions\ActionResponse;

final readonly class OperateResponse implements Responsable
{
    public function __construct(
        public int $impacted,
        public ?ActionResponse $result,
        public ?string $batch = null,
    ) {}

    public function answer(?ActionResponse $result): self
    {
        return new self($this->impacted, $result, $this->batch);
    }

    #[\Override]
    public function toResponse(mixed $request): JsonResponse
    {
        $batch = $this->batch === null ? [] : ['batch' => $this->batch];

        return new JsonResponse(['data' => ['impacted' => $this->impacted, ...$batch, 'result' => $this->result]]);
    }
}
