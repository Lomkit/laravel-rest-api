<?php

namespace Lomkit\Rest\Http;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\OperationEntry;

final readonly class MutateResponse
{
    /** @param list<OperationEntry> $operations */
    private function __construct(public array $operations) {}

    public static function of(MutateRequest $request, Resource $resource): self
    {
        return new self(DB::transaction(function () use ($request, $resource): array {
            $queryBuilder = app()->make(EloquentQueryBuilder::class, ['resource' => $resource, 'query' => null]);
            $resource->mutateQuery($request, $queryBuilder->toBase());

            return $queryBuilder->mutate(['mutate' => array_map(fn (MutationNode $node): array => $node->toArray(), $request->payload())]);
        }));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $grouped = ['created' => [], 'updated' => []];

        foreach ($this->operations as $operation) {
            $entry = $operation->jsonSerialize();
            $grouped[$entry['operation'] === 'create' ? 'created' : 'updated'][] = $entry['key'];
        }

        return $grouped;
    }
}
