<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TypesenseEngine;

class RecordingTypesenseEngine extends TypesenseEngine
{
    /** @var list<array{int, int}> */
    public array $pages = [];

    /** @param Builder<Model> $builder */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $this->pages[] = [(int) $perPage, (int) $page];

        return parent::paginate($builder, $perPage, $page);
    }
}
