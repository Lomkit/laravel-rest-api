<?php

namespace Lomkit\Rest\Console\Generation;

use BackedEnum;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Casts\AsEnumArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Support\Str;
use Lomkit\Rest\Console\Generation\Schema\Column;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;

enum FieldType: string
{
    case Boolean = Boolean::class;
    case Date = Date::class;
    case DateTime = DateTime::class;
    case Enum = Enum::class;
    case Integer = Integer::class;
    case Json = Json::class;
    case Number = Number::class;
    case Text = Text::class;

    private const array JSON_CASTS = [
        'array',
        'collection',
        'json',
        'json:unicode',
        'object',
        'encrypted:array',
        'encrypted:collection',
        'encrypted:json',
        'encrypted:object',
        AsArrayObject::class,
        AsCollection::class,
        AsEncryptedArrayObject::class,
        AsEncryptedCollection::class,
        AsEnumArrayObject::class,
        AsEnumCollection::class,
    ];

    public static function fromCast(mixed $cast): ?self
    {
        if (! is_string($cast)) {
            return null;
        }

        if (is_a($cast, BackedEnum::class, true)) {
            return self::Enum;
        }

        if (in_array($cast, self::JSON_CASTS, true) || in_array(Str::before($cast, ':'), self::JSON_CASTS, true)) {
            return self::Json;
        }

        return match (strtolower(Str::before($cast, ':'))) {
            'bool', 'boolean' => self::Boolean,
            'int', 'integer' => self::Integer,
            'decimal', 'double', 'float', 'real' => self::Number,
            'date', 'immutable_date' => self::Date,
            'custom_datetime', 'datetime', 'immutable_custom_datetime', 'immutable_datetime' => self::DateTime,
            default => null,
        };
    }

    public static function fromColumn(Column $column): ?self
    {
        if ($column->type === 'tinyint(1)') {
            return self::Boolean;
        }

        return match ($column->typeName) {
            'bool', 'boolean' => self::Boolean,
            'bigint', 'int', 'int2', 'int4', 'int8', 'integer', 'mediumint', 'smallint', 'tinyint', 'year' => self::Integer,
            'decimal', 'double', 'float', 'float4', 'float8', 'money', 'numeric', 'real' => self::Number,
            'date' => self::Date,
            'datetime', 'timestamp', 'timestamptz' => self::DateTime,
            'bpchar', 'char', 'citext', 'enum', 'json', 'jsonb', 'longtext', 'mediumtext', 'set', 'text', 'time', 'timetz', 'tinytext', 'uuid', 'varchar' => self::Text,
            default => null,
        };
    }
}
