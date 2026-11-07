<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\OperationEntry;
use Lomkit\Rest\Search\SearchContext;

trait HasEndpointEvents
{
    public function describing(): void {}

    /** @param array<string, mixed> $details */
    public function described(array $details): void {}

    public function searching(SearchContext $search): void {}

    /** @param LengthAwarePaginator<int, Model> $page */
    public function searched(LengthAwarePaginator $page): void {}

    /** @param list<MutationNode> $entries */
    public function mutatingAll(array $entries): void {}

    /** @param list<OperationEntry> $operations */
    public function mutatedAll(array $operations): void {}

    public function operating(Action $action, ActionFields $fields): void {}

    public function operated(Action $action, ActionFields $fields, int $impacted): void {}

    /** @param Collection<int, Model> $models */
    public function destroyingAll(Collection $models): void {}

    /** @param Collection<int, Model> $models */
    public function destroyedAll(Collection $models): void {}

    /** @param Collection<int, Model> $models */
    public function restoringAll(Collection $models): void {}

    /** @param Collection<int, Model> $models */
    public function restoredAll(Collection $models): void {}

    /** @param Collection<int, Model> $models */
    public function forceDestroyingAll(Collection $models): void {}

    /** @param Collection<int, Model> $models */
    public function forceDestroyedAll(Collection $models): void {}
}
