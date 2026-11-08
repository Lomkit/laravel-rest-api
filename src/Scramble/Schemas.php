<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Example;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;

final class Schemas
{
    /** @param array<string, Type> $properties */
    public static function object(array $properties): ObjectType
    {
        $type = new ObjectType;

        foreach ($properties as $name => $property) {
            $type->addProperty($name, $property);
        }

        return $type;
    }

    /**
     * @param  array<string, Type>  $properties
     * @param  list<string>  $optional
     */
    public static function required(array $properties, array $optional = [], string $description = ''): ObjectType
    {
        $type = self::object($properties);
        $type->setRequired(array_values(array_diff(array_keys($properties), $optional)));
        $type->setDescription($description);

        return $type;
    }

    public static function list(Type $items, string $description = ''): ArrayType
    {
        $type = new ArrayType;
        $type->setItems($items);
        $type->setDescription($description);

        return $type;
    }

    /** @param array<string, mixed> $examples */
    public static function requestBody(Operation $operation, ObjectType $body, array $examples = []): void
    {
        $schema = new Schema;
        $schema->type = $body;
        $content = new MediaType($schema);

        foreach ($examples as $name => $example) {
            $content->addExample($name, new Example($example));
        }

        $operation->addRequestBodyObject(RequestBodyObject::make()
            ->required($body->required !== [])
            ->addContent('application/json', $content));
    }

    public static function response(int $status, string $description, Type $type): Response
    {
        $schema = new Schema;
        $schema->type = $type;

        return Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', $type instanceof Reference ? $type : $schema);
    }
}
