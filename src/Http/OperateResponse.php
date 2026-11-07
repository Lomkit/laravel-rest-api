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
    ) {}

    #[\Override]
    public function toResponse(mixed $request): JsonResponse
    {
        return new JsonResponse(['data' => ['impacted' => $this->impacted, 'result' => $this->result]]);
    }
}
