<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use ArrayAccess;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Algolia4Engine;
use Laravel\Scout\Engines\AlgoliaEngine;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;
use Throwable;

final readonly class AlgoliaProfile implements EngineProfile
{
    /** @param AlgoliaEngine<object> $engine */
    public function __construct(private AlgoliaEngine $engine) {}

    #[\Override]
    public function name(): string
    {
        return $this->engine::class;
    }

    #[\Override]
    public function operators(Field $attribute): array
    {
        return EngineOperators::for($attribute);
    }

    #[\Override]
    public function sortKeys(): int
    {
        return 0;
    }

    #[\Override]
    public function ownedOptions(): array
    {
        return ['query', 'filters', 'hitsPerPage', 'page', 'offset', 'length'];
    }

    #[\Override]
    public function exactTotal(): bool
    {
        return true;
    }

    #[\Override]
    public function maxPageSize(): ?int
    {
        return null;
    }

    #[\Override]
    public function window(): ?int
    {
        return null;
    }

    #[\Override]
    public function ensureUsableBy(TextTarget $target): void {}

    #[\Override]
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        return EngineHits::page($this->engine, $builder, $model, $perPage, $page);
    }

    #[\Override]
    public function reachable(ScoutModel $model): ?int
    {
        try {
            $settings = $this->settings($model->indexName());
        } catch (Throwable) {
            return null;
        }

        $limit = is_array($settings) || $settings instanceof ArrayAccess ? ($settings['paginationLimitedTo'] ?? null) : null;

        return is_int($limit) && $limit >= 0 ? $limit : null;
    }

    private function settings(string $index): mixed
    {
        if ($this->engine instanceof Algolia4Engine) {
            return $this->engine->__call('getSettings', [$index]);
        }

        $client = $this->engine->__call('initIndex', [$index]);

        return is_object($client) && method_exists($client, 'getSettings') ? $client->getSettings() : null;
    }
}
