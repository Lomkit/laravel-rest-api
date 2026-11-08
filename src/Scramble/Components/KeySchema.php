<?php

namespace Lomkit\Rest\Scramble\Components;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Lomkit\Rest\Http\Resource;
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

        if (is_int($declared) || is_string($declared)) {
            return $declared;
        }

        return match ($id->resolvedKeyType()) {
            'int' => 1,
            'string' => 'a',
            'uuid' => '00000000-0000-0000-0000-000000000000',
            'ulid' => '00000000000000000000000000',
        };
    }
}
