<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\OperationEntry;

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
        $operations = DB::transaction(function () use ($request, $resource): array {
            $queryBuilder = app()->make(EloquentQueryBuilder::class, ['resource' => $resource, 'query' => null]);
            $resource->mutateQuery($request, $queryBuilder->toBase());

            return $queryBuilder->mutate(['mutate' => array_map(fn (MutationNode $node): array => $node->toArray(), $request->payload())]);
        });
        $block = $request->responseBlock();

        return new self($operations, $block === null ? null : WrittenRecords::read($resource, $block, $operations)->rows());
    }

    #[\Override]
    public function toResponse(mixed $request): JsonResponse
    {
        return new JsonResponse($this->records === null
            ? ['operations' => $this->operations]
            : ['operations' => $this->operations, 'records' => $this->records]);
    }
}
