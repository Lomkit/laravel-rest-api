<?php

namespace Lomkit\Rest\Metrics;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

final class ResultSchemas
{
    public static function value(): ObjectType
    {
        return self::object([
            'value' => self::number(),
            'previous' => self::number()->setDescription('The value of the period before the range'),
        ]);
    }

    public static function trend(): ObjectType
    {
        $other = self::object(['label' => self::label(), 'series' => self::series()]);
        $other->nullable(true);
        $other->setDescription('The splits that the limit folds, present with `splits`');
        $splits = self::list(self::object(['key' => self::key(), 'label' => self::label(), 'series' => self::series()]));
        $splits->setDescription('One series for each value of the split column, present when the trend is split');

        return self::object(['series' => self::series(), 'splits' => $splits, 'other' => $other], ['splits', 'other']);
    }

    public static function partition(): ObjectType
    {
        $other = self::object(['label' => self::label(), 'value' => self::number(), 'previous' => self::previous()], ['previous']);
        $other->nullable(true);
        $other->setDescription('The parts that the limit folds');

        return self::object([
            'parts' => self::list(self::object(['key' => self::key(), 'label' => self::label(), 'value' => self::number(), 'previous' => self::previous()], ['previous'])),
            'other' => $other,
        ]);
    }

    public static function table(): ObjectType
    {
        $values = new ObjectType;
        $values->additionalProperties(self::number());
        $values->setDescription('One value for each column, under the `name` of the column');
        $column = self::object([
            'name' => new StringType,
            'label' => new StringType,
            'aggregate' => (new StringType)->enum([...array_column(Aggregate::cases(), 'value'), Percentile::MEDIAN, Percentile::PERCENTILE]),
            'percentile' => self::fraction(),
        ], ['percentile']);

        return self::object([
            'columns' => self::list($column),
            'rows' => self::list(self::object(['key' => self::key(), 'label' => self::label(), 'values' => $values])),
        ]);
    }

    public static function progress(): ObjectType
    {
        return self::object(['value' => self::number(), 'target' => new NumberType]);
    }

    /**
     * @param  array<string, Type>  $properties
     * @param  list<string>  $optional
     */
    private static function object(array $properties, array $optional = []): ObjectType
    {
        $object = new ObjectType;

        foreach ($properties as $name => $property) {
            $object->addProperty($name, $property);
        }

        $object->setRequired(array_values(array_diff(array_keys($properties), $optional)));

        return $object;
    }

    private static function list(Type $items): ArrayType
    {
        $list = new ArrayType;
        $list->setItems($items);

        return $list;
    }

    private static function series(): ArrayType
    {
        return self::list(self::object(['label' => new StringType, 'value' => self::number()]));
    }

    private static function number(): Type
    {
        return (new NumberType)->nullable(true);
    }

    private static function fraction(): NumberType
    {
        $fraction = new NumberType;
        $fraction->setMin(0);
        $fraction->setMax(1);
        $fraction->setDescription('The percentile of the column, from 0 to 1, present when `aggregate` is `percentile`');

        return $fraction;
    }

    private static function previous(): Type
    {
        return self::number()->setDescription('The value of the period before the range, present when the partition compares two periods');
    }

    private static function label(): Type
    {
        return (new StringType)->nullable(true);
    }

    private static function key(): AnyOf
    {
        $key = new AnyOf;
        $key->setItems([new IntegerType, new StringType, new BooleanType, new NullType]);

        return $key;
    }
}
