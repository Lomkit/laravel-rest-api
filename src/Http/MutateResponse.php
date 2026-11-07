<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Mutations\Mutator;
use Lomkit\Rest\Mutations\OperationEntry;
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
        $operations = $resource::newModel()
            ->getConnection()
            ->transaction(fn (): array => self::write($request, $resource));
        $block = $request->responseBlock();

        return new self($operations, $block === null ? null : WrittenRecords::read($resource, $block, $operations)->rows());
    }

    /** @return list<OperationEntry> */
    private static function write(MutateRequest $request, Resource $resource): array
    {
        $nodes = $request->payload();
        $checks = new WriteChecks($request->rows());

        $checks->authorize($resource, $nodes);

        $resource->mutatingAll($nodes);

        $operations = (new Mutator($request->rows(), $checks))->execute($resource, $nodes);

        $resource->mutatedAll($operations);

        return $operations;
    }

    #[\Override]
    public function toResponse(mixed $request): JsonResponse
    {
        return new JsonResponse($this->records === null
            ? ['operations' => $this->operations]
            : ['operations' => $this->operations, 'records' => $this->records]);
    }
}
