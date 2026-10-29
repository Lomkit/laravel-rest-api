<?php

namespace Lomkit\Rest\Http\Requests\Traits;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Http\Resource;

trait ValidatesResourceKeys
{
    /** @return list<mixed> */
    protected function keyRules(Resource $resource): array
    {
        $model = $resource::newModel();
        $field = $resource->getFields($this)->find($model->getKeyName());

        if ($field instanceof ID) {
            return $field->baseRules();
        }

        return in_array($model->getKeyType(), ['int', 'integer'], true) ? ['integer'] : ['string'];
    }
}
