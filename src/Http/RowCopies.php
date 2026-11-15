<?php

namespace Lomkit\Rest\Http;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use WeakMap;

/** @internal */
final class RowCopies
{
    /** @var WeakMap<Model, Model> */
    private WeakMap $copies;

    private function __construct()
    {
        $this->copies = new WeakMap;
    }

    /**
     * @param  iterable<Model>  $rows
     * @return list<Model>
     */
    public static function of(iterable $rows): array
    {
        $copies = new self;
        $copied = [];

        foreach ($rows as $row) {
            $copied[] = $copies->copy($row);
        }

        return $copied;
    }

    private function copy(Model $model): Model
    {
        if (isset($this->copies[$model])) {
            return $this->copies[$model];
        }

        $copy = (clone $model)->setRawAttributes($model->getAttributes());
        $this->copies[$model] = $copy;

        foreach ($model->getRelations() as $name => $related) {
            $copy->setRelation($name, $this->related($related));
        }

        return $copy;
    }

    private function related(mixed $related): mixed
    {
        if ($related instanceof Model) {
            return $this->copy($related);
        }

        if ($related instanceof Collection) {
            return $related->map(fn (Model $model): Model => $this->copy($model));
        }

        return $related;
    }
}
