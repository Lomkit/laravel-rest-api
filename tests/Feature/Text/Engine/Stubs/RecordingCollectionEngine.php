<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\CollectionEngine;

class RecordingCollectionEngine extends CollectionEngine
{
    /** @var list<Builder<Model>> */
    public array $searches = [];

    /** @var list<array{int, int}> */
    public array $pages = [];

    public function __construct(private readonly bool $runs = true)
    {
        parent::__construct();
    }

    /** @param Builder<Model> $builder */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $this->searches[] = $builder;
        $this->pages[] = [(int) $perPage, (int) $page];

        return $this->runs ? parent::paginate($builder, $perPage, $page) : ['results' => [], 'total' => 0];
    }
}
