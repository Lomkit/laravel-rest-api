<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\ScoutModel;

final class EngineHits
{
    /** @param Builder<Model> $builder */
    public static function page(Engine $engine, Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        $raw = $engine->paginate($builder, $perPage, $page);

        return new EnginePage(
            $model->keys($engine->mapIdsFrom($raw, $model->keyName())),
            $engine->getTotalCount($raw),
        );
    }
}
