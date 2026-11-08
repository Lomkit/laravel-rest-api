<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

final class DetailsEntryTypes
{
    public static function strings(): ArrayType
    {
        return Schemas::list(new StringType);
    }

    public static function operators(string $description = ''): ArrayType
    {
        return Schemas::list((new StringType)->enum(array_column(FilterOperator::cases(), 'value')), $description);
    }

    public static function values(string $description): ArrayType
    {
        return Schemas::list(new MixedType, $description);
    }

    /** @param list<string> $names */
    public static function field(array $names): ObjectType
    {
        return self::fieldEntry(
            $names,
            'With fields.capabilities: accepted in search.selects and returned',
            'With fields.capabilities: accepted in search.sorts',
            'With fields.filter: accepted by search.filters on this field; empty when it is not filterable',
            'With fields.capabilities: writable on create',
            'With fields.capabilities: writable on update',
            ['select', 'sort', 'filter', 'create', 'update', 'rules', 'meta'],
        );
    }

    public static function pivotField(): ObjectType
    {
        return self::fieldEntry(
            [],
            'Returned in the pivot of an included row; false when the model method is not a many-to-many relation',
            'Always false: no endpoint sorts on a pivot column',
            'Accepted by <relation>.pivot.<field> filters; empty when it is not filterable',
            'Writable on create and attach',
            'Writable on update, sync and toggle',
        );
    }

    /** @param list<string> $uriKeys */
    public static function action(array $uriKeys): ObjectType
    {
        return Schemas::required([
            'name' => new StringType,
            'uriKey' => (new StringType)->enum($uriKeys),
            'target' => (new StringType)->enum(array_column(ActionTarget::cases(), 'value')),
            'fields' => Schemas::list(self::input(), 'With actions.fields'),
            'meta' => self::meta('With actions.meta'),
            'destructive' => (new BooleanType)->setDescription('True when the action is declared with destructive(); a hint for clients, the endpoint runs the action the same way'),
            'confirm' => (new StringType)->nullable(true)->setDescription('The text set with confirm(), for clients to show before they run the action; null without one'),
            'confirmation' => (new BooleanType)->setDescription('True when the action declares confirmation(): the endpoint can answer 428 with a signed token, and runs the action when the same body comes back with that token'),
            'authorized' => (new BooleanType)->setDescription('With actions.authorized: true when the user passes the abilities that the action route checks before it reads the request'),
        ], ['fields', 'meta', 'authorized']);
    }

    /** @param list<string> $uriKeys */
    public static function metric(array $uriKeys): ObjectType
    {
        return Schemas::required([
            'name' => new StringType,
            'uriKey' => (new StringType)->enum($uriKeys),
            'type' => (new StringType)->setDescription('The type of the result, given again as data.type in the response of the metric'),
            'ranges' => Schemas::list(Schemas::required(['value' => new IntegerType, 'label' => new StringType]), 'The values that the range key of the request accepts; the first one applies when the request sends none'),
            'meta' => self::meta('With metrics.meta'),
            'authorized' => (new BooleanType)->setDescription('With metrics.authorized: true when the user passes the abilities that the metric route checks before it reads the request'),
        ], ['meta', 'authorized']);
    }

    /** @param list<string> $uriKeys */
    public static function instruction(array $uriKeys): ObjectType
    {
        return Schemas::required([
            'name' => new StringType,
            'uriKey' => (new StringType)->enum($uriKeys),
            'fields' => Schemas::list(self::input(), 'With instructions.fields'),
            'meta' => self::meta('With instructions.meta'),
            'text' => (new BooleanType)->setDescription('Can be sent with search.text on this resource'),
        ], ['fields', 'meta']);
    }

    /** @param list<string> $names */
    public static function scope(array $names): ObjectType
    {
        return Schemas::required([
            'name' => (new StringType)->enum($names),
            'fields' => Schemas::list(self::input(), 'With scopes.fields'),
            'meta' => self::meta('With scopes.meta'),
        ], ['fields', 'meta']);
    }

    private static function input(): ObjectType
    {
        return Schemas::required([
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

    /**
     * @param  list<string>  $names
     * @param  list<string>  $parts
     */
    private static function fieldEntry(array $names, string $select, string $sort, string $operators, string $create, string $update, array $parts = []): ObjectType
    {
        $rules = Schemas::required(
            ['all' => self::strings(), 'create' => self::strings(), 'update' => self::strings()],
            description: 'Validation rules as strings: all on every write, create and update on that operation only',
        );

        return Schemas::required([
            'name' => (new StringType)->enum($names),
            'label' => (new StringType)->nullable(true)->setDescription('The name of the field in validation messages, set with label(); null without one'),
            'type' => new StringType,
            'nullable' => new BooleanType,
            'select' => (new BooleanType)->setDescription($select),
            'sort' => (new BooleanType)->setDescription($sort),
            'filter' => Schemas::required(['operators' => self::operators($operators)]),
            'create' => (new BooleanType)->setDescription($create),
            'update' => (new BooleanType)->setDescription($update),
            'rules' => $rules,
            'meta' => new ObjectType,
            'values' => self::values('Enum fields only: the backing values'),
        ], [...$parts, 'values']);
    }

    private static function meta(string $description): ObjectType
    {
        $meta = new ObjectType;
        $meta->setDescription($description);

        return $meta;
    }
}
