<?php

namespace Lomkit\Rest\Http;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Mutations\OperationEntry;
use Lomkit\Rest\Mutations\RecordKeys;
use Lomkit\Rest\Search\Plan\Columns\ColumnSelection;
use Lomkit\Rest\Search\Plan\KeyedRows;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\KeysGrammar;

final readonly class WrittenRecords
{
    /** @param list<Model|null> $records */
    private function __construct(
        private Response $response,
        private array $records,
    ) {}

    /** @param list<OperationEntry> $operations */
    public static function read(Resource $resource, SearchNode $block, array $operations): self
    {
        $keys = RecordKeys::of(KeysGrammar::idOf($resource), $operations);
        $plan = (new SearchPlanner(new KeyedRows($keys), ColumnSelection::of($resource)))->plan($resource, $block, LimitPolicy::none());
        $rows = $plan->query()->get();

        return new self($resource::newResponse()->plan($plan), $keys->align($rows));
    }

    /** @return list<array<string, mixed>|null> */
    public function rows(): array
    {
        $rows = $this->response->rows(array_filter($this->records));

        return array_map(static fn (int $index): ?array => $rows[$index] ?? null, array_keys($this->records));
    }
}
