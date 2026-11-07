<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

final class DetailsEntryTypes
{
    /**
     * @param  array<string, Type>  $properties
     * @param  list<string>  $optional
     */
    public static function objectOf(array $properties, array $optional = [], string $description = ''): ObjectType
    {
        $type = new ObjectType;

        foreach ($properties as $name => $property) {
            $type->addProperty($name, $property);
        }

        $type->setRequired(array_values(array_diff(array_keys($properties), $optional)));
        $type->setDescription($description);

        return $type;
    }

    public static function listOf(Type $items, string $description = ''): ArrayType
    {
        $type = new ArrayType;
        $type->setItems($items);
        $type->setDescription($description);

        return $type;
    }

    public static function strings(): ArrayType
    {
        return self::listOf(new StringType);
    }

    public static function operators(string $description = ''): ArrayType
    {
        return self::listOf((new StringType)->enum(array_column(FilterOperator::cases(), 'value')), $description);
    }

    public static function values(string $description): ArrayType
    {
        return self::listOf(new MixedType, $description);
    }

    public static function field(): ObjectType
    {
        return self::fieldEntry(
            'Accepted in search.selects and returned',
            'Accepted in search.sorts',
            'Accepted by search.filters on this field; empty when it is not filterable',
            'Writable on create',
            'Writable on update',
        );
    }

    public static function pivotField(): ObjectType
    {
        return self::fieldEntry(
            'Returned in the pivot of an included row; false when the model method is not a many-to-many relation',
            'Always false: no endpoint sorts on a pivot column',
            'Accepted by <relation>.pivot.<field> filters; empty when it is not filterable',
            'Writable on create and attach',
            'Writable on update, sync and toggle',
        );
    }

    public static function action(): ObjectType
    {
        return self::objectOf([
            'name' => new StringType,
            'uriKey' => new StringType,
            'target' => (new StringType)->enum(array_column(ActionTarget::cases(), 'value')),
            'fields' => self::listOf(self::input()),
            'meta' => new ObjectType,
            'destructive' => (new BooleanType)->setDescription('True when the action is declared with destructive(); a hint for clients, the endpoint runs the action the same way'),
            'confirm' => (new StringType)->nullable(true)->setDescription('The text set with confirm(), for clients to show before they run the action; null without one'),
        ]);
    }

    public static function instruction(): ObjectType
    {
        return self::objectOf([
            'name' => new StringType,
            'uriKey' => new StringType,
            'fields' => self::listOf(self::input()),
            'meta' => new ObjectType,
            'text' => (new BooleanType)->setDescription('Can be sent with search.text on this resource'),
        ]);
    }

    public static function scope(): ObjectType
    {
        return self::objectOf([
            'name' => new StringType,
            'fields' => self::listOf(self::input()),
            'meta' => new ObjectType,
        ]);
    }

    private static function input(): ObjectType
    {
        return self::objectOf([
            'name' => new StringType,
            'label' => (new StringType)->nullable(true)->setDescription('The name of the field in validation messages, set with label(); null without one'),
            'type' => new StringType,
            'nullable' => new BooleanType,
            'default' => new MixedType,
            'rules' => self::strings(),
            'meta' => new ObjectType,
            'values' => self::values('Enum fields only: the backing values'),
        ], ['values']);
    }

    private static function fieldEntry(string $select, string $sort, string $operators, string $create, string $update): ObjectType
    {
        $rules = self::objectOf(
            ['all' => self::strings(), 'create' => self::strings(), 'update' => self::strings()],
            description: 'Validation rules as strings: all on every write, create and update on that operation only',
        );

        return self::objectOf([
            'name' => new StringType,
            'label' => (new StringType)->nullable(true)->setDescription('The name of the field in validation messages, set with label(); null without one'),
            'type' => new StringType,
            'nullable' => new BooleanType,
            'select' => (new BooleanType)->setDescription($select),
            'sort' => (new BooleanType)->setDescription($sort),
            'filter' => self::objectOf(['operators' => self::operators($operators)]),
            'create' => (new BooleanType)->setDescription($create),
            'update' => (new BooleanType)->setDescription($update),
            'rules' => $rules,
            'meta' => new ObjectType,
            'values' => self::values('Enum fields only: the backing values'),
        ], ['values']);
    }
}
