<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Actions\Rows\RowCap;
use Lomkit\Rest\Support\Translation;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\EngineQuery;

final readonly class EngineTargetKeys
{
    public function __construct(
        private EngineQuery $query,
        private int $chunkCount,
        private ?RowCap $cap,
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

        $this->cap?->check($count);

        if ($this->limit === null) {
            $this->ensureReachable($first->total);
        }

        return self::distinct($this->collect($first, $perPage, $count), $count);
    }

    /** @return list<int|string> */
    private function uncounted(EnginePage $first, int $perPage): array
    {
        $keys = $this->collect($first, $perPage, $this->cap?->limit($this->limit) ?? $this->limit ?? PHP_INT_MAX);

        $this->cap?->check(count($keys));

        return self::distinct($keys, count($keys));
    }

    private function ensureReachable(int $total): void
    {
        $reachable = $this->query->reachable()
            ?? throw self::refusal('rest::actions.engine_unreported');

        if ($total >= $reachable) {
            throw self::refusal('rest::actions.engine_window', ['max' => (string) $reachable]);
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
                throw self::refusal('rest::actions.engine_window', ['max' => (string) $window]);
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
            throw self::refusal('rest::actions.engine_results_changed');
        }

        return $keys;
    }

    /** @param array<string, string> $replace */
    private static function refusal(string $key, array $replace = []): ValidationException
    {
        return ValidationException::withMessages(['search' => [Translation::line($key, $replace)]]);
    }
}
