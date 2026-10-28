<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Requests\RestRequest;
use ReflectionClass;

trait DeclaresFields
{
    private ?FieldSet $calculatedFields = null;

    /** @return list<Field> */
    public function fields(RestRequest $request): array
    {
        return [];
    }

    public function getFields(RestRequest $request): FieldSet
    {
        return $this->calculatedFields ??= $this->declaredFields($request);
    }

    private function declaredFields(RestRequest $request): FieldSet
    {
        $fields = FieldSet::of($this->fields($request), static::class.'::fields()');

        if (! (new ReflectionClass(static::$model))->isInstantiable()) {
            return $fields;
        }

        return $fields->bindTo(static::newModel());
    }
}
