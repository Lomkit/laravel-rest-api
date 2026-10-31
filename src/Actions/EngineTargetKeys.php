<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\EngineQuery;

final readonly class EngineTargetKeys
{
    public function __construct(
        private EngineQuery $query,
        private int $chunkCount,
        private int $maxResources,
        private ?int $limit = null,
    ) {}

    /** @return list<int|string> */
    public function resolve(): array
    {
        $paging = $this->query->paging();
        $perPage = $paging->pageSize($this->chunkCount);
        $first = $this->query->page($perPage, 1);

        return $paging->exactTotal() ? $this->counted($first, $perPage) : $this->uncounted($first, $perPage);
    }

    /** @return list<int|string> */
    private function counted(EnginePage $first, int $perPage): array
    {
        $count = min($first->total, $this->limit ?? $first->total);

        if ($count > $this->maxResources) {
            throw self::refusal("This action can be run on at most {$this->maxResources} resources; narrow the search.");
        }

        if ($this->limit === null) {
            $this->ensureReachable($first->total);
        }

        return self::distinct($this->collect($first, $perPage, $count), $count);
    }

    /** @return list<int|string> */
    private function uncounted(EnginePage $first, int $perPage): array
    {
        $keys = $this->collect($first, $perPage, min($this->limit ?? PHP_INT_MAX, $this->maxResources + 1));

        if (count($keys) > $this->maxResources) {
            throw self::refusal("This action can be run on at most {$this->maxResources} resources; narrow the search.");
        }

        return self::distinct($keys, count($keys));
    }

    private function ensureReachable(int $total): void
    {
        $reachable = $this->query->reachable()
            ?? throw self::refusal('The search engine does not report how many results this search can reach; give the search a limit.');

        if ($reachable <= $this->maxResources && $total >= $reachable) {
            throw self::refusal("The search engine returns at most {$reachable} results for this search; narrow the search.");
        }
    }

    /** @return list<int|string> */
    private function collect(EnginePage $first, int $perPage, int $wanted): array
    {
        $keys = $first->keys;
        $last = $first;
        $window = $this->query->paging()->window();

        for ($page = 2; count($keys) < $wanted && count($last->keys) === $perPage; $page++) {
            if ($window !== null && $perPage * $page > $window) {
                throw self::refusal("The search engine returns at most {$window} results for this search; narrow the search.");
            }

            $last = $this->query->page($perPage, $page);
            $keys = [...$keys, ...$last->keys];
        }

        return array_slice($keys, 0, $wanted);
    }

    /**
     * @param  list<int|string>  $keys
     * @return list<int|string>
     */
    private static function distinct(array $keys, int $count): array
    {
        if (count($keys) !== $count || count(array_unique($keys)) !== $count) {
            throw self::refusal('The search results changed while the action was resolving them; run the action again.');
        }

        return $keys;
    }

    private static function refusal(string $message): ValidationException
    {
        return ValidationException::withMessages(['search' => [$message]]);
    }
}
