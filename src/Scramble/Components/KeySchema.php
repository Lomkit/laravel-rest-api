<?php

namespace Lomkit\Rest\Scramble\Components;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\FieldExample;
use Lomkit\Rest\Scramble\FieldSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Validation\KeysGrammar;

final class KeySchema
{
    public static function list(Resource $resource): ArrayType
    {
        $keys = Schemas::list(FieldSchema::of(KeysGrammar::idOf($resource)));
        $keys->setMin(1);
        $keys->setMax($resource->context()->limits()->sizes->resources);
        $keys->setUniqueItems(true);

        return $keys;
    }

    public static function example(Resource $resource): int|string
    {
        $id = KeysGrammar::idOf($resource);
        $declared = $id->getExamples()[0] ?? null;

        return is_int($declared) || is_string($declared) ? $declared : FieldExample::key($id);
    }
}
