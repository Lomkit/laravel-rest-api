<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PagedMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use RuntimeException;

class FacetingMeilisearchEngine extends PagedMeilisearchEngine
{
    /** @var list<array<mixed>> */
    public array $options = [];

    public ?string $refusal = null;

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>|null  $faceting
     */
    public function __construct(public array $result, ?array $faceting = ['maxValuesPerFacet' => 100, 'sortFacetValuesBy' => ['*' => 'alpha']])
    {
        parent::__construct(new SettingsClient(static fn (): mixed => $faceting ?? throw new RuntimeException('The API key cannot read the settings.')));
    }

    /** @param Builder<Model> $builder */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $this->options[] = $builder->options;

        if ($this->refusal !== null) {
            throw new RuntimeException($this->refusal);
        }

        return [...parent::paginate($builder, $perPage, $page), ...$this->result];
    }
}
